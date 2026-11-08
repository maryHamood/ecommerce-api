import http from 'k6/http';
import { check } from 'k6';

export let options = {
    vus: 1,
    iterations: 2,
};

export default function () {
    let res = http.get('http://127.0.0.1:8000/api/v1/products?category_id=1');

    check(res, {
        'status is 200': (r) => r.status === 200,
    });


    console.log(`Request Time: ${res.timings.duration} ms`);
}
