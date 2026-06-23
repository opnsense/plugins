import LogServerMenu from './LogServerMenu';

const LogServerCollection = Backbone.Collection.extend({
    model: LogServerMenu,
    url: function () {
        return `/api/nginx/logs/${this.logType}`;
    },
    initialize: function (params) {
        this.logType = params.logType;
    }
});

export default LogServerCollection;
