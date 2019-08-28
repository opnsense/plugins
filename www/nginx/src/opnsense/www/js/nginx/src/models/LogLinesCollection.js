import LogLine from "./LogLine";

const LogLinesCollection = Backbone.Collection.extend({
    model: LogLine,
    url: function () {
        return `/api/nginx/logs/${this.logType}/${this.uuid}`;
    },
    initialize: function () {
        this.logType = 'none';
        this.uuid = 'none';
    },
    parse: function(response) {
        if ('error' in response) {
            return [];
        }
        return response;
    },
    filter_collection: function(filter_model) {
        const filter_model_keys = filter_model.keys();
        return this.filter(function (model) {
            if (!model) {
                return false;
            }
            for (let i = 0; i < filter_model_keys.length; i++) {
                const property = filter_model_keys[i];
                if (typeof(filter_model.get(property)) !== "string"
                    || filter_model.get(property).length === 0) {
                    continue;
                }
                if (!model.has(property)) {
                    return false;
                }
                if (!model.get(property).includes(filter_model.get(property))) {
                    return false;
                }
            }
            return true;
        });
    }
});

export default LogLinesCollection;
