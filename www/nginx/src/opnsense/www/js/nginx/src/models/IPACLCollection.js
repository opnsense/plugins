export default Backbone.Collection.extend({
    initialize: function() {
        let that = this;
        $('#ipacl\\.data').change(function () {
            that.regenerateFromView();
        });
    },
    regenerateFromView: function () {
        let data = JSON.parse($('#ipacl\\.data').val());
        if (!_.isArray(data)) {
            data = [];
        }
        this.reset(data);
    }
});