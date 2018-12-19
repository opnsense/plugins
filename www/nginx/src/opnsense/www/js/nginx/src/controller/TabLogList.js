import TabTemplateCollection from '../templates/TabCollection.html';

let TabLogList = Backbone.View.extend({

    tagName: 'li',
    events: {
        "click .mainentry": "mainMenuClick",
        "click .menuEntry": "menuEntryClick"
    },

    initialize: function(data) {
        this.listenTo(this.collection, "sync", this.render);
        this.listenTo(this.collection, "update", this.render);
        this.logview = data.logview;
    },

    render: function() {
        this.$el.html('');
        this.renderCollection();
    },

    renderCollection: function() {
        this.$el.addClass('dropdown');
        this.$el.html('');
        this.$el.append(
            TabTemplateCollection({model: this.collection, name: this.model.attributes.name})
        );
    },
    mainMenuClick: function () {
        if (this.collection.models[0]) {
            this.handleElementClick(this.collection.models[0].id);
        }
    },
    menuEntryClick: function (event) {
        this.handleElementClick(event.target.dataset['modelUuid']);
    },
    handleElementClick: function (uuid) {
        this.logview.get_log(this.model.get('logType'), uuid);
    }
});
export default TabLogList;
