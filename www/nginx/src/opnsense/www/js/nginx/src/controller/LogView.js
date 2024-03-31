import LogLine from '../templates/LogLine.html';
import LogColumn from '../templates/LogColumn.html';
import logViewer from '../templates/logviewer.html';
import LogLinesCollection from "../models/LogLinesCollection";
import LogColumnModel from "../models/LogColumn";
import noDataAvailable from '../templates/noDataAvailable.html';

const LogViewLine = Backbone.View.extend({
    tagName: 'tr',
    initialize: function (data) {
        this.type = data.type;
        this.log_fields_visible = data.log_fields_visible;
    },

    render: function () {
        this.$el.html(LogLine({log_fields_visible: this.log_fields_visible, model: this.model}));
    },
});

const LogViewColumns = Backbone.View.extend({
    tagName: 'tr',
    className: 'filter',
    initialize: function (data) {
        this.log_fields_visible = data.log_fields_visible;
    },

    render: function() {
        this.log_fields_visible.forEach((field) => this.$el.append(LogColumn({field: field, model: this.model})));
    }
});

const LogView = Backbone.View.extend({

    tagName: 'div',
    className: 'content-box tab-content',
    events: {
        "keyup .filter input": "update_filter",
        "click #paging_first": "page_first",
        "click #paging_back": "page_back",
        "click #refresh": "update",
        "click #paging_forward": "page_forward",
        "click #paging_last": "page_last",
        "change #entrycount": "change_entry_count",
        "click .ngx-dropdown-item": "toggle_column",
    },
    page_entry_count: 100,
    filter_delay: -1,

    initialize: function() {
        this.collection = new LogLinesCollection();
        this.listenTo(this.collection, "sync", this.render);
        this.listenTo(this.collection, "update", this.render);
        this.listenTo(this.collection.filter_model, "change", this.render);
        this.type = '';
    },

    render: function() {
        // set logline fields
        // all the fields are visible by default
        // users choice is stored in browser localStorage
        this.logFields = [];
        let uid = this.collection.uuid;
        let type = this.type;
        switch (type) {
            case 'accesses':
                this.logFields.push({id: "time", header: "Time"},
                                    {id: "remote_ip", header: "Remote IP"},
                                    {id: "username", header: "Username"},
                                    {id: "status", header: "Status"},
                                    {id: "size", header: "Size"},
                                    {id: "referer", header: "Referer"},
                                    {id: "user_agent", header: "User Agent"},
                                    {id: "forwarded_for", header: "Forwarded For"},
                                    {id: "request_line", header: "Request Line"});
                break;
            case 'errors':
            case 'stream_errors':
                this.logFields.push({id: "date", header: "Date"},
                                    {id: "time", header: "Time"},
                                    {id: "severity", header: "Severity"},
                                    {id: "number", header: "Number"},
                                    {id: "message", header: "Message"});
                break;
            default:
                // stream access
                this.logFields.push({id: "time", header: "Time"},
                                    {id: "remote_ip", header: "Remote IP"},
                                    {id: "status", header: "Status"},
                                    {id: "bytes_sent", header: "Bytes Sent"},
                                    {id: "bytes_received", header: "Bytes Rcvd"},
                                    {id: "session_time", header: "Session Time"});
        }
        this.logFields.forEach( (field) => {
            field.visible = localStorage.getItem('visibleColumns[' + type + '][' + uid + '][' + field.id + ']') !== 'false';
        });

        this.logFieldsVisible = _.filter(this.logFields, ['visible', true]);
        // fields are ready

        // create/update column headers
        let thead = this.$('thead');
        if (thead.children().length < 1) {
            const logColumns = new LogViewColumns({log_fields_visible: this.logFieldsVisible, model: this.collection.filter_model});
            logColumns.render();
            thead.html(logColumns.$el);
        }

        let tbody = this.$('tbody');
        if (tbody.length < 1) {
            if (this.collection.length !== 0) {
                this.$el.html(logViewer({log_type: this.type, log_fields: this.logFields, log_fields_visible: this.logFieldsVisible, model: this.collection.filter_model}));
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
        }

        this.$('#entrycountdisplay').html(this.page_entry_count);
        this.$('#currentpage').html(this.current_page + 1);
        this.$('#pagecount').html(this.collection.page_count);
        this.$('#totalcount').html(this.collection.total_entries);
        this.$('#resultcount').html(this.collection.displayed_entries);

        if (this.current_page >= this.collection.page_count - 1) {
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
    },

    render_one: function(parent_element, model) {
        const logline = new LogViewLine({type: this.type, log_fields_visible: this.logFieldsVisible, model: model});
        logline.render();
        parent_element.append(logline.$el);
    },

    get_log: function(type, uuid, fileNo) {
        this.collection.uuid = uuid;
        this.collection.logType = type;
        this.collection.fileNo = fileNo;
        this.type = type;
        this.current_page = 0;
        this.$el.html('');
        this.collection.filter_model.clear();
        this.update();
    },

    update: function () {
        this.collection.page = this.current_page;
        this.collection.pageSize = this.page_entry_count;
        this.collection.fetch();
    },

    update_filter: function (event) {
        clearTimeout(this.filter_delay);
        const element = event.target;
        this.collection.filter_model.set(element.name, $(element).val());
        this.current_page = 0;

        // Delay update to avoid multiple requests during typing
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
        this.current_page = this.collection.page_count - 1;
        this.update();
    },

    change_entry_count: function (event) {
        this.page_entry_count = event.target.value;
        this.current_page = 0;
        this.update();
    },

    toggle_column: function (event) {
        event.stopPropagation();
        let uid = this.collection.uuid;
        let type = this.type;
        let field = $(event.currentTarget).find('input').prop('value');
        // toggle visibility
        localStorage.setItem('visibleColumns[' + type + '][' + uid + '][' + field + ']', !_.find(this.logFields, { 'id': field }).visible);
        // unset filter for this column (if any) so as not to confuse the user
        this.collection.filter_model.unset(field, {silent: true});
        // reset table header and update data
        this.$('thead').html('');
        this.update();
    }
});

export default LogView;
