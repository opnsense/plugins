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
        "click #paging_first": "page_first",
        "click #paging_back": "page_back",
        "click #paging_forward": "page_forward",
        "click #paging_last": "page_last",
        "change #entrycount": "change_entry_count",
    },
    page_entry_count: 100,
    current_page: 0,
    filter_delay: -1,
    //current_filtered_collection: null,

    initialize: function() {
        this.collection = new LogLinesCollection();
        //this.filter_model = new Backbone.Model();
        this.listenTo(this.collection, "sync", this.clear_and_render);
        this.listenTo(this.collection, "update", this.clear_and_render);
        this.listenTo(this.collection.filter_model, "change", this.clear_and_render);
        this.type = '';
    },

    render: function() {
        let tbody = this.$('tbody');
        if (tbody.length < 1) {
            if (this.collection.length !== 0) {
                this.$el.html(logViewer({log_type: this.type, model: this.collection.filter_model}));
                tbody = this.$('tbody');
            } else {
                this.$el.html(noDataAvailable);
            }
        }
        else {
            tbody.html('');
        }
        if (this.collection.length !== 0) {
            if (this.current_filtered_collection == null) {
                this.collection.forEach(
                    (model) => this.render_one(tbody, model)
                );
            }

            this.$('#entrycountdisplay').html(this.page_entry_count);
            this.$('#currentpage').html(this.current_page + 1);
            this.$('#pagecount').html(this.collection.page_count + 1);
            this.$('#totalcount').html(this.collection.total_entries);
            this.$('#resultcount').html(this.collection.displayed_entries);

            if (this.current_page >= this.collection.page_count) {
                this.$('#paging_last').addClass("disabled");
                this.$('#paging_forward').addClass("disabled");
            }
            else {
                this.$('#paging_last').removeClass("disabled");
                this.$('#paging_forward').removeClass("disabled");
            }

            if (this.current_page <= 0) {
                this.$('#paging_back').addClass("disabled");
                this.$('#paging_first').addClass("disabled");
            }
            else {
                this.$('#paging_back').removeClass("disabled");
                this.$('#paging_first').removeClass("disabled");
            }
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
        this.collection.filter_model.clear();
        this.update();
    },
    update: function () {
        this.collection.page = this.current_page;
        this.collection.pageSize = this.page_entry_count;
        this.collection.fetch();
    },
    clear_and_render: function() {
        this.render();
    },
    update_filter: function (event) {
        clearTimeout(this.filter_delay);
        const element = event.target;
        this.collection.filter_model.set(element.name, $(element).val());
        this.current_page = 0;

        this.filter_delay = setTimeout(function(instance) {
            instance.update();
        }, 500, this);
    },
    page_first: function () {
        this.current_page = 0;
        this.update();
    },
    page_back: function () {
        if (this.current_page > 0) {
            this.current_page--;
            this.update();
        }
    },
    page_forward: function () {
        if (this.current_page < this.collection.page_count) {
            this.current_page++;
            this.update();
        }
    },
    page_last: function () {
        this.current_page = this.collection.page_count;
        this.update();
    },
    change_entry_count: function (event) {
        this.page_entry_count = event.target.value;
        this.current_page = 0;
        this.update();
    }
});

export default LogView;
