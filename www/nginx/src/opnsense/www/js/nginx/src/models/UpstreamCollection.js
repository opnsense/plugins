const UpstreamCollection = Backbone.Collection.extend({
    url: '/api/nginx/settings/searchupstream',
    parse: function(response) {
        return response.rows;
    }
});
export default UpstreamCollection;
