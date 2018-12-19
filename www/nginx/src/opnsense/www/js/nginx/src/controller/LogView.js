import accessLogLine from '../templates/AccessLogLine.html';
import streamAccessLogLine from '../templates/StreamAccessLogLine.html';
import errorLogLine from '../templates/ErrorLogLine.html';
import logViewer from '../templates/logviewer.html';
import LogLinesCollection from "../models/LogLinesCollection";
import noDataAvailable from '../templates/noDataAvailable.html';


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
        } else if (this.type === 'stream_accesses') {
            return streamAccessLogLine;
        } else {
            return errorLogLine;
        }
    },
});

const LogView = Backbone.View.extend({

    tagName: 'div',
    className: 'content-box tab-content',
    events: {
        "keyup .filter input": "update_filter",
        "click #paging_back": "page_back",
        "click #paging_forward": "page_forward",
        "change #entrycount": "change_entry_count",
    },
    page_entry_count: 100,
    current_page: 0,
    current_filtered_collection: null,

    initialize: function() {
        this.collection = new LogLinesCollection();
        this.filter_model = new Backbone.Model();
        this.listenTo(this.collection, "sync", this.clear_and_render);
        this.listenTo(this.collection, "update", this.clear_and_render);
        this.listenTo(this.filter_model, "change", this.clear_and_render);
        this.type = '';
    },

    render: function() {
        let tbody = this.$el.find('tbody');
        if (tbody.length < 1) {
            if (this.collection.length !== 0) {
                this.$el.html(logViewer({log_type: this.type, model: this.filter_model}));
                tbody = this.$el.find('tbody');
            } else {
                this.$el.html(noDataAvailable);
            }
        }
        else {
            tbody.html('');
        }
        if (this.collection.length !== 0) {
            if (this.current_filtered_collection == null) {
                this.current_filtered_collection = this.collection.filter_collection(this.filter_model);
            }
            const index_begin = this.current_page * this.page_entry_count;
            const index_end = index_begin + this.page_entry_count;
            this.current_filtered_collection.slice(index_begin, index_end).forEach(
                (model) => this.render_one(tbody, model)
            );
        }
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
    clear_and_render: function() {
        this.current_filtered_collection = null;
        this.render();
    },
    update_filter: function (event) {
        const element = event.target;
        this.filter_model.set(element.name, $(element).val());
    },
    page_back: function () {
        if (this.current_page > 0) {
            this.current_page--;
            this.render();
        }
    },
    page_forward: function () {
        if ((this.current_page + 1) * this.page_entry_count < this.collection.length) {
            this.current_page++;
            this.render();
        }
    },
    change_entry_count: function (event) {
        this.page_entry_count = event.target.value;
        this.current_page = 0;
        this.render();
    }
});
export default LogView;
