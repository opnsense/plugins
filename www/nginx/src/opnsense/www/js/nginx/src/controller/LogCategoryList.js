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
    },

    render: function() {
        this.$el.attr('role', 'tablist');
        this.$el.html('');
        this.collection.forEach((element) => this.render_one(element));
        this.render_single_tabs();
    },

    render_one: function(element) {
        const servers = new LogCollection(
            {
                uuid: element.get('url'),
                logType: element.get('logType')
            }
        );
        const logList = new TabLogList({
            collection: servers,
            model: element,
            logview: this.logview
        });
        this.$el.append(logList.$el);
        servers.fetch();
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
