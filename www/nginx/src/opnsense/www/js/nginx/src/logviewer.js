import LogServerCollection from './models/LogServerCollection';
import LogCategoryList from './controller/LogCategoryList';
import LogView from './controller/LogView';

const logview = new LogView();

const type = $('#logapplication').data('log');
const servers = new LogServerCollection({
    logType: type
});

const menu = new LogCategoryList({
    collection: servers,
    logview: logview,
    logType: type
});

$('#logapplication')
    .append(menu.$el)
    .append(logview.$el);
servers.fetch();
menu.render();
