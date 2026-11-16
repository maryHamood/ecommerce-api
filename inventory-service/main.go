
package main

import (
	"context"
	"errors"
	"fmt"
	"log"
	"net"
	"os"

	pb "inventory-service/protos"

	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"gorm.io/driver/mysql"
	"gorm.io/gorm"
	"gorm.io/gorm/clause"
)

type Inventory struct {
	ProductID string `gorm:"primaryKey;size:191"`
	Amount    int
}

type Reservation struct {
	ID        uint   `gorm:"primaryKey"`
	OrderID   string `gorm:"type:varchar(191);not null;uniqueIndex:idx_order_product,priority:1"`
	ProductID string `gorm:"type:varchar(191);not null;uniqueIndex:idx_order_product,priority:2"`
	Quantity  int    `gorm:"not null"`
	Status    string `gorm:"type:varchar(20);not null;default:active;index"`
}

type server struct {
	pb.UnimplementedInventoryServiceServer
	db *gorm.DB
}

var (
	ErrInsufficientStock = errors.New("insufficient stock")
	ErrReservationConflict = errors.New("reservation conflicts with existing reservation")
	ErrActiveReservation = errors.New("product has active reservations")
	ErrInventoryNotFound = errors.New("inventory not found")
)

func operation(success bool, message string) *pb.OperationResponse {
	return &pb.OperationResponse{
		Success: success,
		Message: message,
	}
}

func (s *server) InitInventory(ctx context.Context, req *pb.InitRequest) (*pb.OperationResponse, error) {
	if req.ProductId == "" || req.InitialQuantity < 0 {
		return operation(false, "Invalid product or quantity"), nil
	}

	inventory := Inventory{
		ProductID: req.ProductId,
		Amount:    int(req.InitialQuantity),
	}

	result := s.db.Where("product_id = ?", req.ProductId).
		FirstOrCreate(&inventory, inventory)

	if result.Error != nil {
		return nil, status.Error(codes.Internal, "Failed to initialize inventory")
	}

	return operation(true, "Inventory initialized"), nil
}

func (s *server) DeleteInventory(ctx context.Context, req *pb.DeleteRequest) (*pb.OperationResponse, error) {
	if req.ProductId == "" {
		return operation(false, "Invalid product ID"), nil
	}

	err := s.db.Transaction(func(tx *gorm.DB) error {
		var count int64

		if err := tx.Model(&Reservation{}).
			Where("product_id = ? AND status = ?", req.ProductId, "active").
			Count(&count).Error; err != nil {
			return err
		}

		if count > 0 {
			return ErrActiveReservation
		}

		result := tx.Where("product_id = ?", req.ProductId).
			Delete(&Inventory{})

		if result.Error != nil {
			return result.Error
		}

		if result.RowsAffected == 0 {
			return ErrInventoryNotFound
		}

		return nil
	})

	if errors.Is(err, ErrActiveReservation) {
		return operation(false, "Cannot delete inventory with active reservations"), nil
	}

	if errors.Is(err, ErrInventoryNotFound) {
		return operation(false, "Inventory not found"), nil
	}

	if err != nil {
		return nil, status.Error(codes.Internal, "Failed to delete inventory")
	}

	return operation(true, "Inventory deleted"), nil
}

func (s *server) CheckStock(ctx context.Context, req *pb.StockRequest) (*pb.StockResponse, error) {
	var inventory Inventory

	err := s.db.First(&inventory, "product_id = ?", req.ProductId).Error

	if errors.Is(err, gorm.ErrRecordNotFound) {
		return &pb.StockResponse{Quantity: 0}, nil
	}

	if err != nil {
		return nil, status.Error(codes.Internal, "Failed to check stock")
	}

	return &pb.StockResponse{
		Quantity: int32(inventory.Amount),
	}, nil
}

func (s *server) ReserveStock(ctx context.Context, req *pb.ReserveRequest) (*pb.OperationResponse, error) {
	if req.OrderId == "" || len(req.Items) == 0 {
		return operation(false, "Invalid order or empty items"), nil
	}

	// Aggregate duplicate product IDs before reserving.
	items := make(map[string]int)

	for _, item := range req.Items {
		if item == nil || item.ProductId == "" || item.Quantity <= 0 {
			return operation(false, "Invalid reservation item"), nil
		}

		items[item.ProductId] += int(item.Quantity)
	}

	err := s.db.Transaction(func(tx *gorm.DB) error {
		// Lock existing reservations for this order.
		var existing []Reservation

		if err := tx.Clauses(clause.Locking{Strength: "UPDATE"}).
			Where("order_id = ?", req.OrderId).
			Find(&existing).Error; err != nil {
			return err
		}

		// Idempotency: an identical active reservation is already successful.
		if len(existing) > 0 {
			if len(existing) != len(items) {
				return ErrReservationConflict
			}

			for _, reservation := range existing {
				quantity, found := items[reservation.ProductID]

				if !found ||
					reservation.Quantity != quantity ||
					reservation.Status != "active" {
					return ErrReservationConflict
				}
			}

			return nil
		}

		for productID, quantity := range items {
			result := tx.Model(&Inventory{}).
				Where("product_id = ? AND amount >= ?", productID, quantity).
				UpdateColumn("amount", gorm.Expr("amount - ?", quantity))

			if result.Error != nil {
				return result.Error
			}

			if result.RowsAffected != 1 {
				return ErrInsufficientStock
			}

			reservation := Reservation{
				OrderID:   req.OrderId,
				ProductID: productID,
				Quantity:  quantity,
				Status:    "active",
			}

			if err := tx.Create(&reservation).Error; err != nil {
				return err
			}
		}

		return nil
	})

	if errors.Is(err, ErrInsufficientStock) {
		return operation(false, "Insufficient stock for one or more products"), nil
	}

	if errors.Is(err, ErrReservationConflict) {
		return operation(false, "Order already has a different or released reservation"), nil
	}

	if err != nil {
		return nil, status.Error(codes.Internal, "Failed to reserve stock")
	}

	return operation(true, "Stock reserved"), nil
}

func (s *server) ReleaseStock(ctx context.Context, req *pb.ReleaseRequest) (*pb.OperationResponse, error) {
	if req.OrderId == "" {
		return operation(false, "Invalid order ID"), nil
	}

	err := s.db.Transaction(func(tx *gorm.DB) error {
		var reservations []Reservation

		// Lock active reservations to prevent concurrent double release.
		if err := tx.Clauses(clause.Locking{Strength: "UPDATE"}).
			Where("order_id = ? AND status = ?", req.OrderId, "active").
			Find(&reservations).Error; err != nil {
			return err
		}

		// No active reservations: already released or never reserved.
		if len(reservations) == 0 {
			return nil
		}

		for _, reservation := range reservations {
			result := tx.Model(&Inventory{}).
                Where("product_id = ?", reservation.ProductID).
                UpdateColumn("amount", gorm.Expr("amount + ?", reservation.Quantity))
			if result.Error != nil {
				return result.Error
			}

			if result.RowsAffected != 1 {
				return fmt.Errorf("inventory missing for product %s", reservation.ProductID)
			}

			result = tx.Model(&Reservation{}).
				Where("id = ? AND status = ?", reservation.ID, "active").
				Update("status", "released")

			if result.Error != nil {
				return result.Error
			}

			if result.RowsAffected != 1 {
				return errors.New("reservation status update failed")
			}
		}

		return nil
	})

	if err != nil {
		return nil, status.Error(codes.Internal, "Failed to release stock")
	}

	return operation(true, "Stock released"), nil
}

func main() {
	dbHost := os.Getenv("DB_HOST")
	dbUser := os.Getenv("DB_USER")
	dbPass := os.Getenv("DB_PASS")
	dbName := os.Getenv("DB_NAME")

	dsn := fmt.Sprintf(
		"%s:%s@tcp(%s:3306)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		dbUser, dbPass, dbHost, dbName,
	)

	db, err := gorm.Open(mysql.Open(dsn), &gorm.Config{})
	if err != nil {
		log.Fatalf("Failed to connect to database: %v", err)
	}

	if err := db.AutoMigrate(&Inventory{}, &Reservation{}); err != nil {
		log.Fatalf("Failed to migrate database: %v", err)
	}

	lis, err := net.Listen("tcp", ":50051")
	if err != nil {
		log.Fatalf("Failed to listen: %v", err)
	}

	grpcServer := grpc.NewServer()

	pb.RegisterInventoryServiceServer(grpcServer, &server{db: db})

	log.Println("Inventory gRPC Server running on port 50051")

	if err := grpcServer.Serve(lis); err != nil {
		log.Fatalf("gRPC server failed: %v", err)
	}
}
