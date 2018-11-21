export default Backbone.Collection.extend({
    initialize: function() {
        const that = this;
        $('#snihostname\\.data').on('change', function () {
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
