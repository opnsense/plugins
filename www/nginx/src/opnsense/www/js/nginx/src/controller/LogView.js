import accessLogLine from '../templates/AccessLogLine.html';
import errorLogLine from '../templates/ErrorLogLine.html';
import logViewer from '../templates/logviewer.html';
import LogLinesCollection from "../models/LogLinesCollection";

const LogView = Backbone.View.extend({

    tagName: 'div',
    className: 'content-box tab-content',
    events: {
        "focusout .filter input": "update_filter"
    },

    initialize: function() {
        this.collection = new LogLinesCollection();
        this.filter_model = new Backbone.Model();
        this.listenTo(this.collection, "sync", this.render);
        this.listenTo(this.collection, "update", this.render);
        this.listenTo(this.filter_model, "change", this.render);
        this.type = '';
    },

    render: function() {
        this.$el.html('');
        let table_content = '';
        let template = this.get_template();
        this.filter_collection().forEach((model) => table_content += template({model: model}));
        this.$el.html(logViewer({table_body: table_content, log_type: this.type, model: this.filter_model}));
    },
    get_template: function() {
        if (this.type === 'accesses') {
            return accessLogLine;
        } else {
            return errorLogLine;
        }
    },
    filter_collection: function() {
        const that = this;
        const filter_model = that.filter_model;
        const filter_model_keys = that.filter_model.keys();
        console.log(filter_model);
        return this.collection.filter(function (model) {
            if (!model) {
                return false;
            }
            for (let i = 0; i < filter_model_keys.length; i++) {
                const property = filter_model_keys[i];
                if (typeof(that.filter_model.get(property)) !== "string"
                    || that.filter_model.get(property).length === 0) {
                    continue;
                }
                if (!model.get(property).includes(that.filter_model.get(property))) {
                    return false;
                }
            }
            return true;
        });
    },
    get_log: function(type, uuid) {
        this.collection.uuid = uuid;
        this.collection.logType = type;
        this.type = type;
        this.filter_model.clear();
        this.update();
    },
    update: function () {
        this.collection.fetch();
    },
    update_filter: function (event) {
        const element = event.target;
        this.filter_model.set(element.name, $(element).val());
    }
});
export default LogView;