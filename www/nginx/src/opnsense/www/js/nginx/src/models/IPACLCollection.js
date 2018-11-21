export default Backbone.Collection.extend({
    initialize: function() {
        const that = this;
        $('#ipacl\\.data').on('change', function () {
            that.regenerateFromView();
        });
    },
    regenerateFromView: function () {
        let data = $('#ipacl\\.data').data('data');
        if (!_.isArray(data)) {
            data = [];
        }
        this.reset(data);
    }
});
