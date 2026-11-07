import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '10s', target: 50 },
        { duration: '20s', target: 50 },
        { duration: '10s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'],
    },
};

export default function () {

    let res = http.get('http://127.0.0.1:8000/api/v1/products?category_id=1', {
        headers: { 'Accept': 'application/json' },
    });


    check(res, {
        'status is 200': (r) => r.status === 200,
    });

    sleep(1);
}
