export const KeyValueMapFieldEntryUpstreamMap = Backbone.View.extend({

    tagName: 'div',
    attributes: {'class': 'row'},
    events: {
        'keyup .key': function () {
            this.model.set('hostname', this.key.value);
        },
        'change .value': function () {
            this.model.set('upstream', this.value.value);
        },
        "click .delete" : "deleteEntry"
    },
    key: null,
    value: null,
    delBtn: null,
    first: null,
    second: null,
    third: null,
    upstreamCollection: null,
    initialize: function (params) {
        this.upstreamCollection = params.upstreamCollection;
        this.listenTo(this.upstreamCollection, "update reset add remove", this.regenerate_list);
        this.first = document.createElement('div');
        this.first.classList.add('col-sm-5');
        this.key = document.createElement('input');
        this.first.append(this.key);
        this.key.type = 'text';
        this.key.classList.add('key');
        this.key.value = this.model.get('hostname');

        this.second = document.createElement('div');
        this.second.classList.add('col-sm-5');
        this.value = document.createElement('select');
        this.second.append(this.value);
        this.value.classList.add('value');
        this.value.classList.add('form-control');
        this.value.value = this.model.get('upstream');

        this.third = document.createElement('div');
        this.third.classList.add('col-sm-2');
        this.third.style.textAlign = 'right';
        this.delBtn = document.createElement("button");
        this.delBtn.classList.add('delete');
        this.delBtn.classList.add('btn');
        this.delBtn.innerHTML = '<span class="fa fa-trash"></span>';
        this.third.append(this.delBtn);
        if (!this.model.has('upstream') ||
            this.upstreamCollection.where ({'uuid' : this.model.get('upstream')}).length === 0) {
            if (this.upstreamCollection.length > 0) {
                this.model.set('upstream', this.upstreamCollection.at(0).get('uuid'));
            }
        }


        this.$el.append(this.first).append(this.second).append(this.third);
    },
    render: function() {
        $(this.key).val(this.model.get('hostname'));
        this.regenerate_list();
        $(this.value).val(this.model.get('upstream'));
    },
    deleteEntry: function (e) {
        e.preventDefault();
        this.collection.remove(this.model);
    },
    regenerate_list: function () {
        // backup value
        const v = $(this.value);
        // clear the dropdown
        v.html('');
        this.upstreamCollection.each(
            (mdl) => v.append(`<option value="${mdl.escape('uuid')}">${mdl.escape('description')}</option>`)
        );
        // restore
        v.val(this.model.get('upstream'));
        v.selectpicker('refresh');
    }
});
export const KeyValueMapFieldEntryACL = Backbone.View.extend({

    tagName: 'div',
    attributes: {'class': 'row'},
    events: {
        'keyup .key': function () {
            this.model.set('network', this.key.value);
        },
        'change .value': function () {
            this.model.set('action', this.value.value);
        },
        "click .delete" : "deleteEntry"
    },
    key: null,
    value: null,
    delBtn: null,
    first: null,
    second: null,
    third: null,
    upstreamCollection: null,
    initialize: function (params) {
        this.upstreamCollection = params.upstreamCollection;
        this.listenTo(this.upstreamCollection, "update reset add remove", this.regenerate_list);
        this.first = document.createElement('div');
        this.first.classList.add('col-sm-5');
        this.key = document.createElement('input');
        this.first.append(this.key);
        this.key.type = 'text';
        this.key.classList.add('key');
        this.key.value = this.model.get('network');

        this.second = document.createElement('div');
        this.second.classList.add('col-sm-5');
        this.value = document.createElement('select');
        this.second.append(this.value);
        this.value.classList.add('value');
        this.value.classList.add('form-control');
        this.value.value = this.model.get('action');

        this.third = document.createElement('div');
        this.third.classList.add('col-sm-2');
        this.third.style.textAlign = 'right';
        this.delBtn = document.createElement("button");
        this.delBtn.classList.add('delete');
        this.delBtn.classList.add('btn');
        this.delBtn.innerHTML = '<span class="fa fa-trash"></span>';
        this.third.append(this.delBtn);


        this.$el.append(this.first).append(this.second).append(this.third);
    },
    render: function() {
        $(this.key).val(this.model.get('network'));
        this.regenerate_list();
        $(this.value).val(this.model.get('action'));
    },
    deleteEntry: function (e) {
        e.preventDefault();
        this.collection.remove(this.model);
    },
    regenerate_list: function () {
        // backup value
        const v = $(this.value);
        // clear the dropdown
        v.html('');
        this.upstreamCollection.each(
            (mdl) => v.append(`<option value="${mdl.escape('value')}">${mdl.escape('name')}</option>`)
        );
        // restore
        v.val(this.model.get('action'));
        v.selectpicker('refresh');
    }
});
