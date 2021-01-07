import LogServerCollection from './models/LogServerCollection';
import LogCategoryList from './controller/LogCategoryList';
import LogView from './controller/LogView';

// Skeleton with header (navigation) and footer (pagination)
const logview = new LogView();
// Get type of log to display from volt view (data-log HTML attribute)
const type = $('#logapplication').data('log');
// Query (HTTP or stream) server list
const servers = new LogServerCollection({
    logType: type
});
// Render tabs with server logs (one tab per server)
const menu = new LogCategoryList({
    collection: servers,
    logview: logview,
    logType: type // 'errors', 'accesses' or 'global'
});

// Place log application to volt template
$('#logapplication')
    .append(menu.$el)
    .append(logview.$el);

if (type != 'global') {
    // Global error log does not require server list
    servers.fetch();
}
else {
    // Update of server list triggers render() which does not
    // occur for global error log
    menu.render();
}
