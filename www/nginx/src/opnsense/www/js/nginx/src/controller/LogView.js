import accessLogLine from '../templates/AccessLogLine.html';
import errorLogLine from '../templates/ErrorLogLine.html';
import logViewer from '../templates/logviewer.html';
import LogLinesCollection from "../models/LogLinesCollection";


const LogViewLine = Backbone.View.extend({
    tagName: 'tr',
    initialize: function (data) {
        this.type = data.type;
    },

    render: function () {
        this.$el.html(this.get_template()({model: this.model}));
    },

    get_template: function() {
        if (this.type === 'accesses') {
            return accessLogLine;
        } else {
            return errorLogLine;
        }
    },
});

const LogView = Backbone.View.extend({

    tagName: 'div',
    className: 'content-box tab-content',
    events: {
        "keyup .filter input": "update_filter"
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
        let tbody = this.$el.find('tbody');
        if (tbody.length < 1) {
            this.$el.html(logViewer({log_type: this.type, model: this.filter_model}));
            tbody = this.$el.find('tbody');
        }
        else {
            tbody.html('');
        }
        this.collection.filter_collection(this.filter_model).forEach(
            (model) => this.render_one(tbody, model)
        );
    },
    render_one: function(parent_element, model) {
        const logline = new LogViewLine({type: this.type, model: model});
        logline.render();
        parent_element.append(logline.$el);
    },
    get_log: function(type, uuid) {
        this.collection.uuid = uuid;
        this.collection.logType = type;
        this.type = type;
        this.$el.html('');
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