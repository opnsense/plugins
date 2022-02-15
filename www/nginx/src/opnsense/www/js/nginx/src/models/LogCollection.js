import LogFileMenuEntry from './LogFileMenuEntry';

const LogCollection = Backbone.Collection.extend({
    model: LogFileMenuEntry,
    url: function () {
        return `/api/nginx/logs/${this.logType}/${this.uuid}`;
    },
    initialize: function (params) {
        this.logType = params.logType;
        this.uuid = params.uuid;
    }
});

export default LogCollection;
