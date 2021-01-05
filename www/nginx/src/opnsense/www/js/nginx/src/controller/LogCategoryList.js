import LogCollection from "../models/LogCollection";
import TabLogList from "./TabLogList";
import SingleTab from "./SingleTab";

let LogCategoryList = Backbone.View.extend({
    tagName: "ul",
    className: "nav nav-tabs",

    initialize: function(data) {
        this.listenTo(this.collection, "sync",   this.render);
        this.listenTo(this.collection, "update", this.render);
        this.logview = data.logview;
        this.logType = data.logType;
    },

    render: function() {
        this.$el.attr('role', 'tablist');
        this.$el.html('');

        if (this.logType == 'global') {
            this.render_single_tabs();
        }
        else {
            this.collection.forEach((element) => this.render_one(element));
        }
    },

    render_one: function(element) {
        const files = new LogCollection(
            {
                uuid: element.get('id'),
                logType: this.logType
            }
        );
        const logList = new TabLogList({
            collection: files,
            model: element,
            logType: this.logType,
            logview: this.logview
        });
        this.$el.append(logList.$el);
        files.fetch();
    },
    render_single_tabs: function () {
        const single_tab = new SingleTab({
            logview: this.logview,
            log_name: 'global',
            visible_name: 'Global Error Log',
            log_type: 'errors'});
        single_tab.render();
        this.$el.append(single_tab.$el);

    }
});

export default LogCategoryList;
