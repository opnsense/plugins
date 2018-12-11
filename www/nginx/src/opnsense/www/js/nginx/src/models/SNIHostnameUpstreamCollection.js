export default Backbone.Collection.extend({
    initialize: function() {
        let that = this;
        $('#snihostname\\.data').change(function () {
            that.regenerateFromView();
        });
    },
    regenerateFromView: function () {
        let data = $('#snihostname\\.data').data('data');
        if (!_.isArray(data)) {
            data = [];
        }
        this.reset(data);
    }
});
