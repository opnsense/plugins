import LogCollection from "../models/LogCollection";
import TabLogList from "./TabLogList";

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
    }
});

export default LogCategoryList;