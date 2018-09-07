import LogFileMenuEntry from './LogFileMenuEntry';

const LogCollection = Backbone.Collection.extend({
    model: LogFileMenuEntry,
    url: function () {
        return '/api/nginx/logs/' + this.logType;
    },
    initialize: function (params) {
        this.logType = params.logType;
    }
});

export default LogCollection;
