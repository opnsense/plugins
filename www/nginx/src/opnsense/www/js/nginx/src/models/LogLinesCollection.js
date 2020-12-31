import LogLine from "./LogLine";

const LogLinesCollection = Backbone.Collection.extend({
    model: LogLine,
    url: function () {
        return `/api/nginx/logs/${this.logType}/${this.uuid}/${this.page}/${this.pageSize}/${this.create_filter()}`;
    },
    initialize: function () {
        this.logType = 'none';
        this.uuid = 'none';
        this.page = 0;
        this.pageSize = 0;
        this.filter_model = new Backbone.Model();
    },
    parse: function(response) {
        if ('error' in response) {
            return [];
        }
        else {
            this.page_count = response.pages;
            this.total_entries = response.total;
            this.displayed_entries = response.found;
            return response.lines;
        }
    },
    create_filter: function() {
        return JSON.stringify(this.filter_model);
    }
});

export default LogLinesCollection;
