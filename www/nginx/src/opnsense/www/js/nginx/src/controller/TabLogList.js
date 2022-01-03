import TabTemplateCollection from '../templates/TabCollection.html';

let TabLogList = Backbone.View.extend({

    tagName: 'li',
    events: {
        "click .mainentry": "mainMenuClick",
        "click .menuEntry": "menuEntryClick",
        "click .dropdown-toggle": "menuDropdownClick"
    },

    initialize: function(data) {
        this.listenTo(this.collection, "update", this.render);
        this.logType = data.logType;
        this.logview = data.logview;
        this.render();
    },

    render: function() {
        this.$el.html('');
        this.renderCollection();
    },

    renderCollection: function() {
        this.$el.addClass('dropdown');
        if (this.model.get('id') == "global") {
            this.$el.addClass('active');
            this.logview.get_log('errors', 'global', -1);
        }
        this.$el.html('');
        this.$el.append(
            TabTemplateCollection({
                model: this.collection,
                id: this.model.get('id'),
                name: this.model.has('server_name') ? this.model.get('server_name') : "Port " + this.model.get('port')
            })
        );
    },
    mainMenuClick: function () {
        if (this.collection.models[0]) {
            this.handleElementClick(this.model.get('id'), -1);
            $(`#tab_${this.model.get('id')} li`).removeClass('active');
            $(`#subtab_item_${this.model.get('id')}_${this.collection.models[0].get('number')}`).parent().addClass('active');
        }
    },
    menuDropdownClick: function (event) {
        this.collection.fetch();
    },
    menuEntryClick: function (event) {
        this.handleElementClick(event.target.dataset['modelUuid'], event.target.dataset['modelFileno']);
    },
    handleElementClick: function (uuid, fileNo) {
        this.logview.get_log(this.logType, uuid, fileNo);
    }
});
export default TabLogList;
