export default Backbone.Collection.extend({
    initialize: function() {
        let that = this;
        $('#snihostname\\.data').change(function () {
            that.regenerateFromView();
        });
    },
    regenerateFromView: function () {
        let data = JSON.parse($('#snihostname\\.data').val());
        if (!_.isArray(data)) {
            data = [];
        }
        this.reset(data);
    }
});