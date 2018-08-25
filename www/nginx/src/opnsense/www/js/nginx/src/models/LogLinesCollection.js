import LogLine from "./LogLine";

const LogLinesCollection = Backbone.Collection.extend({
    model: LogLine,
    url: function () {
        return `/api/nginx/logs/${this.logType}/${this.uuid}`;
    },
    initialize: function () {
        this.logType = 'none';
        this.uuid = 'none';
    }
});

export default LogLinesCollection;