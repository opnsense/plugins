import SingleTab from "../templates/single_tab.html"

let LogCategoryList = Backbone.View.extend({
    tagName: "li",

    initialize: function(data) {
        this.logview = data.logview;
        this.log_name = data.log_name;
        this.visible_name = data.visible_name;
        this.log_type = data.log_type;
    },
    events: {
        "click .mainentry": "handleElementClick"
    },
    log_name: null,
    log_type: null,
    visible_name: null,

    render: function() {
        this.$el.html(SingleTab({'name': this.visible_name}));
    },
    handleElementClick: function () {
        this.logview.get_log(this.log_type, this.log_name);
    }
});

export default LogCategoryList;
