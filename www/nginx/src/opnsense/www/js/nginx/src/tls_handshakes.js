import TLSFingerprints from "./models/TLSFingerprints";
import FingerPrintsTemplate from "./templates/Fingerprints.html";
import FingerPrintTemplate from "./templates/Fingerprint.html";
import FingerPrintDialogContent from "./templates/FingerPrintDialogContent.html";


const model = new TLSFingerprints();

const FingerPrintSingle = Backbone.View.extend({
    initialize: function(params) {
        this.ua = params.ua;
    },
    events: {
        "click button": "trust_fingerprint"
    },
    render: function () {
        this.$el.html(FingerPrintTemplate(this.model.toJSON()));
    },
    trust_fingerprint: function() {
        const that = this;
        BootstrapDialog.show({
            type: BootstrapDialog.TYPE_INFO,
            title: "Save new fingerprint",
            message: $('<div></div>').html(FingerPrintDialogContent({})),
            buttons: [{
                label: "Save",
                cssClass: 'btn-primary',
                icon: 'fa fa-floppy-o ',
                action: function (dlg) {
                    // bind to controller
                    that.handle_trust(
                        dlg.$modalBody.find('#fp_description').val(),
                        dlg.$modalBody.find('#fp_trusted').is(':checked')
                    );
                    dlg.close();

                }
            }, {
                label: 'Close',
                action: function (dlg) {
                    dlg.close();
                }
            }]
        });
    },
    handle_trust: function (description, trusted) {
        ajaxCall(
            "/api/nginx/settings/addtls_fingerprint",
            {
                'tls_fingerprint': {
                    'curves' : this.model.get('curves'),
                    'ciphers': this.model.get('ciphers'),
                    'user_agent': this.ua,
                    'trusted': trusted ? '1' : '0',
                    'description': description
                }
            },
            function (data, status) {
            }
        );
    }
});

const FingerPrintList = Backbone.View.extend({
    initialize: function (params) {
        this.ua = params.ua;
        this.render();
    },

    render: function () {
        const that = this;
        this.$el.html(FingerPrintsTemplate({ua: this.ua}));
        const content_holder = this.$el.find('.content_holder');
        const chart_holder = this.$el.find('.chart_holder');
        const chart_data = this.collection.map(function (d) {
            return {label: d.get('ciphers') + "||" + d.get('curves'), value: d.get('count')};
        });
        this.collection.forEach(function (fingerprint) {
            const row = new FingerPrintSingle({'model': fingerprint, 'ua': that.ua});
            content_holder.append(row.$el);
            row.render();
        });
        try {
            nv.addGraph(function () {
                const chart = nv.models.pieChart();
                chart.x(function (d) {
                    return d.label;
                });
                chart.y(function (d) {
                    return d.value;
                });
                chart.showLabels(false);
                chart.labelType("value");
                chart.donut(true);
                chart.donutRatio(0.2);

                d3.select(chart_holder[0])
                    .datum(chart_data)
                    .transition().duration(350)
                    .call(chart);

                return chart;
            });
        } catch (e) {
            console.log(e);
        }
    }
});

const FingerprintMain = Backbone.View.extend({
    initialize: function() {
        this.listenTo(this.model, "sync",   this.render);
    },

    render: function () {
        this.$el.html('');
        this.render_all(this.model.attributes);
    },
    render_all(attributes) {
        for (const ua in attributes) {
            // skip loop if the property is from prototype
            if (attributes.hasOwnProperty(ua)) {
                const fingerprints = new Backbone.Collection(attributes[ua]);
                const fingerprints_view = new FingerPrintList({'collection': fingerprints, 'ua': ua});
                this.$el.append(fingerprints_view.$el);
            }
        }
    },
});

const fpm = new FingerprintMain({'model': model});
$('#tls_handshakes_application').append(fpm.$el);

model.fetch();
