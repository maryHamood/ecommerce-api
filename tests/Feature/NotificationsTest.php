<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Notifications\NotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('notification-test-secret-', 3)]);
    }

    private function account(string $phone, string $role = 'customer'): User
    {
        return User::create([
            'name' => $role,
            'phone' => $phone,
            'password' => Hash::make('HandoffDemo!2026'),
            'role' => $role,
        ]);
    }

    private function headers(User $user): array
    {
        auth('api')->forgetUser();
        app('tymon.jwt')->unsetToken();

        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    public function test_full_flow_with_admin_and_two_customers(): void
    {
        $admin = $this->account('+963900000101', 'admin');
        $owner = $this->account('+963900000102');
        $other = $this->account('+963900000103');
        $product = Product::create(['name' => 'Demo', 'price' => 25, 'amount' => 10]);

        $login = $this->postJson('/api/v1/auth/login', [
            'phone' => $owner->phone,
            'password' => 'HandoffDemo!2026',
        ])->assertOk()->assertJsonPath('data.user.role', 'customer');
        $ownerHeaders = ['Authorization' => 'Bearer ' . $login->json('data.token')];

        $this->getJson('/api/v1/products', $ownerHeaders)->assertOk();
        $created = $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $ownerHeaders)->assertCreated()->assertJsonPath('data.status', 'pending');
        $id = $created->json('data.id');

        $this->patchJson('/api/v1/admin/orders/' . $id . '/status', [
            'status' => 'shipped',
        ], $this->headers($admin))->assertOk()->assertJsonPath('data.status', 'shipped');

        $this->assertDatabaseHas('orders', ['id' => $id, 'status' => 'shipped']);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'message' => 'Your order #' . $id . ' is now shipped.',
        ]);
        $this->getJson('/api/v1/notifications', $this->headers($owner))
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.message', 'Your order #' . $id . ' is now shipped.')
            ->assertJsonStructure([['id', 'message', 'createdAt']]);
        $this->getJson('/api/v1/notifications', $this->headers($other))
            ->assertOk()->assertExactJson([]);
        $this->getJson('/api/v1/my-orders', $this->headers($owner))
            ->assertOk()->assertJsonPath('data.0.status', 'shipped');
        $this->getJson('/api/v1/admin/orders', $this->headers($admin))->assertOk();
    }

    public function test_missing_or_invalid_token_returns_the_existing_error_format(): void
    {
        $expected = ['success' => false, 'message' => 'Unauthenticated.', 'errors' => null];
        $this->getJson('/api/v1/notifications')->assertStatus(401)->assertExactJson($expected);
        auth('api')->forgetUser();
        $this->getJson('/api/v1/notifications', ['Authorization' => 'Bearer invalid'])
            ->assertStatus(401)->assertExactJson($expected);
    }

    public function test_query_user_id_cannot_change_the_receiver(): void
    {
        $owner = $this->account('+963900000102');
        $other = $this->account('+963900000103');
        app(NotificationSender::class)->send($owner->id, 'Private message');

        $this->getJson('/api/v1/notifications?userId=' . $owner->id . '&user_id=' . $owner->id,
            $this->headers($other))->assertOk()->assertExactJson([]);
    }

    public function test_newest_first_with_deterministic_ties_and_exact_public_fields(): void
    {
        $owner = $this->account('+963900000102');
        $this->travelTo(now()->startOfSecond());
        app(NotificationSender::class)->send($owner->id, 'Older');
        $this->travel(1)->seconds();
        app(NotificationSender::class)->send($owner->id, 'Newer');
        app(NotificationSender::class)->send($owner->id, 'Newest tie');

        $response = $this->getJson('/api/v1/notifications', $this->headers($owner))->assertOk();
        $response->assertJsonPath('0.message', 'Newest tie')->assertJsonPath('1.message', 'Newer')
            ->assertJsonPath('2.message', 'Older');
        $this->assertSame(['id', 'message', 'createdAt'], array_keys($response->json('0')));
        $this->assertMatchesRegularExpression('/Z$/', $response->json('0.createdAt'));
        $this->travelBack();
    }

    public function test_customers_cannot_change_status(): void
    {
        $owner = $this->account('+963900000102');
        $order = Order::create(['user_id' => $owner->id, 'total' => 25, 'status' => 'pending']);

        $this->patchJson('/api/v1/admin/orders/' . $order->id . '/status', [
            'status' => 'shipped',
        ], $this->headers($owner))->assertStatus(403)->assertJsonPath('success', false);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_invalid_status_and_missing_order_do_not_notify(): void
    {
        $admin = $this->account('+963900000101', 'admin');
        $owner = $this->account('+963900000102');
        $order = Order::create(['user_id' => $owner->id, 'total' => 25, 'status' => 'pending']);

        $this->patchJson('/api/v1/admin/orders/' . $order->id . '/status', [
            'status' => 'invalid',
        ], $this->headers($admin))->assertStatus(422)->assertJsonPath('success', false);
        $this->patchJson('/api/v1/admin/orders/999999/status', [
            'status' => 'shipped',
        ], $this->headers($admin))->assertNotFound()->assertJsonPath('message', 'Resource not found.');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_same_status_retry_does_not_create_another_notification(): void
    {
        $admin = $this->account('+963900000101', 'admin');
        $owner = $this->account('+963900000102');
        $order = Order::create(['user_id' => $owner->id, 'total' => 25, 'status' => 'pending']);

        foreach (['shipped', 'shipped'] as $status) {
            $this->patchJson('/api/v1/admin/orders/' . $order->id . '/status', [
                'status' => $status,
            ], $this->headers($admin))->assertOk();
        }

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notification_failure_rolls_back_the_status_change(): void
    {
        $admin = $this->account('+963900000101', 'admin');
        $owner = $this->account('+963900000102');
        $order = Order::create(['user_id' => $owner->id, 'total' => 25, 'status' => 'pending']);
        $sender = new class implements NotificationSender {
            public bool $savedBeforeFailure = false;

            public function send(int $userId, string $message): void
            {
                $this->savedBeforeFailure = Order::firstOrFail()->status === 'shipped';

                throw new \RuntimeException('Simulated notification failure');
            }
        };
        $this->app->instance(NotificationSender::class, $sender);

        $this->patchJson('/api/v1/admin/orders/' . $order->id . '/status', [
            'status' => 'shipped',
        ], $this->headers($admin))->assertStatus(500)->assertJsonPath('message', 'Server Error');
        $this->assertTrue($sender->savedBeforeFailure);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_migration_preserves_legacy_notifications_and_can_rollback(): void
    {
        $owner = $this->account('+963900000102');
        $migration = require database_path('migrations/2026_10_28_000001_add_message_to_notifications_table.php');
        $migration->down();
        $id = \Illuminate\Support\Facades\DB::table('notifications')->insertGetId([
            'user_id' => $owner->id,
            'title' => 'Existing title',
            'body' => 'Existing body',
            'is_read' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration->up();

        $this->assertDatabaseHas('notifications', [
            'id' => $id, 'message' => 'Existing body', 'is_read' => true,
        ]);
        $migration->down();
        $this->assertDatabaseHas('notifications', ['id' => $id, 'body' => 'Existing body']);
        $migration->up();
    }

    public function test_test_ui_and_external_assets_keep_security_headers(): void
    {
        foreach (['/test-ui', '/test-ui/app.js', '/test-ui/app.css'] as $url) {
            $this->get($url)->assertOk()->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }
}
