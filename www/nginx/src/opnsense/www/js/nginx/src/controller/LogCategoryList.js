import LogCollection from "../models/LogCollection";
import TabLogList from "./TabLogList";
import SingleTab from "./SingleTab";

let LogCategoryList = Backbone.View.extend({
    tagName: "ul",
    className: "nav nav-tabs",

    initialize: function(data) {
        this.listenTo(this.collection, "update", this.render);
        this.logview = data.logview;
        this.logType = data.logType;
    },

    render: function() {
        this.$el.attr('role', 'tablist');
        this.$el.html('');

        if (this.logType == 'global') {
            this.render_global_error_tab();
        }
        else {
            this.collection.forEach((element) => this.render_one_server(element));
        }
    },

    render_one_server: function(element) {
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

    render_global_error_tab: function () {
        const files = new LogCollection(
            {
                uuid: 'global',
                logType: 'errors'
            }
        );
        const logList = new TabLogList({
            collection: files,
            model: new Backbone.Model({
                server_name: 'Global Error Log',
                id: 'global'
            }),
            logType: 'errors',
            logview: this.logview
        });
        this.$el.append(logList.$el);
        files.fetch();
    }
});

export default LogCategoryList;
