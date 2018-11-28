export const defaultEndpoints = new Backbone.Collection([
    {
        "name": 'HTTP Access Logs',
        "logType" : 'accesses'
    },
    {
        "name": 'HTTP Error Logs',
        "logType" : 'errors'
    },
    {
        "name": 'Stream Access Logs',
        "logType" : 'stream_accesses'
    },
    {
        "name": 'Stream Error Logs',
        "logType" : 'stream_errors'
    }
]);
