export default Backbone.View.extend({
    tagName: 'div',
    attributes: {'class': 'container-fluid'},
    child_views: [],
    createModel: null,
    upstreamCollection: null,
    initialize: function (params) {
        this.dataField = $(params.dataField);
        this.entryclass = params.entryclass;
        this.createModel = params.createModel;
        this.upstreamCollection = params.upstreamCollection;
        this.listenTo(this.collection, "add remove reset", this.render);
        this.listenTo(this.collection, "change", this.update);
        // inject our table holder
        this.dataField.after(this.$el);
    },
    events: {
        "click .add": "addEntry"
    },
    render: function () {
        // clear table
        this.child_views.forEach((model) => model.remove());
        this.$el.html('');
        this.child_views = [];
        this.update();
        this.collection.each((model) => {
            const childView = new this.entryclass({
                model: model,
                collection: this.collection,
                upstreamCollection: this.upstreamCollection
            });
            this.child_views.push(childView);
            this.$el.append(childView.$el);
            childView.render();
        });
        this.$el.append($(`
            <div class="row">
                <button class="btn btn-primary pull-right add">
                    <span class="fa fa-plus"></span>
                </button>
            </div>`));
    },
    update: function () {
        this.dataField.data('data', this.collection.toJSON());
    },
    addEntry: function (e) {
        e.preventDefault();
        this.collection.add(this.createModel());
    }
});
