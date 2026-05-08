/**
 * Создание и настройка запросов к JSON-RPC 2.0
 * https://www.jsonrpc.org/specification
 */
class Request {
    #method  = 'POST';
    #headers = {'Content-Type': 'application/json'};
    #jsonrpc = '2.0';
    body;

    constructor(method, params, id) {
        this.body = Object.create(null);
        this.body.id      = id || Date.now();
        this.body.jsonrpc = this.#jsonrpc;
        this.body.method  = method || null;
        this.body.params  = params || Object.create(null);
    }

    reset(method, params, id) {
        this.body.id      = id || Date.now();
        this.body.method  = method || null;
        this.body.params  = params || Object.create(null);
    }

    setMethod(method) {
        this.body.method = method || null;
    }

    setParam(key, value) {
        if ('string' === typeof key && 'undefined' !== typeof value) {
            this.body.params[key] = value;
        }
    }

    removeParam(key) {
        if (key in this.body.params) {
            delete this.body.params[key];
        }
    }

    out() {
        return {
            'method':  this.#method,
            'headers': this.#headers,
            'body':    JSON.stringify(this.body)
        };
    }
}
