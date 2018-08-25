import accessLogLine from '../templates/AccessLogLine.html';
import errorLogLine from '../templates/ErrorLogLine.html';
import logViewer from '../templates/logviewer.html';
import LogLinesCollection from "../models/LogLinesCollection";

const LogView = Backbone.View.extend({

    tagName: 'div',
    className: 'content-box tab-content',
    events: {
        "click .mainentry": "mainMenuClick"
    },

    initialize: function() {
        this.collection = new LogLinesCollection();
        this.listenTo(this.collection, "sync", this.render);
        this.listenTo(this.collection, "update", this.render);
        this.type = '';
    },

    render: function() {
        this.$el.html('');
        let table_content = '';
        let template = this.get_template();
        this.collection.forEach((model) => table_content += template({model: model}));
        this.$el.html(logViewer({table_body: table_content, log_type: this.type}));
    },
    get_template: function() {
        if (this.type === 'accesses') {
            return accessLogLine;
        } else {
            return errorLogLine;
        }
    },
    get_log: function(type, uuid) {
        this.collection.uuid = uuid;
        this.collection.logType = type;
        this.type = type;
        this.update();
    },
    update: function () {
        this.collection.fetch();
    }
});
export default LogView;