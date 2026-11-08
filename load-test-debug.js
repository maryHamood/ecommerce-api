import http from 'k6/http';
import { check } from 'k6';

export let options = {
    vus: 1,
    iterations: 5,
};

export default function () {

    // let res = http.get('http://127.0.0.1:8000/api/v1/products?category_id=1&debug=true&without_with=true');
    let res = http.get('http://127.0.0.1:8000/api/v1/products?category_id=1&debug=true');
    check(res, {
        'status is 200': (r) => r.status === 200,
    });


    let body = JSON.parse(res.body);
    if (body.data && body.data.queries_count !== undefined) {
        console.log(`Queries Count: ${body.data.queries_count}`);
    }
}
