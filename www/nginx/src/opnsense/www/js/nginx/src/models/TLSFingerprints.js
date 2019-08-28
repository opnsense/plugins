
const TLSFingerprints = Backbone.Model.extend({
    url: '/api/nginx/logs/tls_handshakes'
});

export default TLSFingerprints;
