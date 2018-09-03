import {defaultEndpoints} from './config';
import LogCategoryList from './controller/LogCategoryList';
import LogView from './controller/LogView';

const logview = new LogView();

const menu = new LogCategoryList({
    collection: defaultEndpoints,
    logview: logview
});

$(document.getElementById('logapplication'))
    .append(menu.$el)
    .append(logview.$el);
menu.render();
