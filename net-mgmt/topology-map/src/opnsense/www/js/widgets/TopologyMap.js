/*
 * Copyright (C) 2026
 * All rights reserved.
 */

export default class TopologyMap extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 60;
    }

    getMarkup() {
        let $container = $('<div></div>');
        $container.append(this.createTable('topologymap-table', {
            headerPosition: 'left'
        }));
        return $container;
    }

    async onMarkupRendered() {
        const rows = [
            [[this.translations.interfaces], $('<span id="tm-interfaces">').prop('outerHTML')],
            [[this.translations.hosts], $('<span id="tm-hosts">').prop('outerHTML')],
            [[this.translations.neighbors], $('<span id="tm-neighbors">').prop('outerHTML')],
            [[this.translations.nodes], $('<span id="tm-nodes">').prop('outerHTML')],
            [[this.translations.links], $('<span id="tm-links">').prop('outerHTML')],
            [[this.translations.geoPoints], $('<span id="tm-geo-points">').prop('outerHTML')]
        ];

        super.updateTable('topologymap-table', rows);
    }

    async onWidgetTick() {
        const summary = await this.ajaxCall('/api/topologymap/service/summary');
        const geomap = await this.ajaxCall('/api/topologymap/service/geomap');

        if (!summary || summary.status !== 'ok' || !summary.summary) {
            ['interfaces', 'hosts', 'neighbors', 'nodes', 'links'].forEach((name) => {
                $('#tm-' + name).text('-');
            });
            $('#tm-geo-points').text('-');
            return;
        }

        $('#tm-interfaces').text(summary.summary.interfaces ?? '-');
        $('#tm-hosts').text(summary.summary.hosts ?? '-');
        $('#tm-neighbors').text(summary.summary.neighbors ?? '-');
        $('#tm-nodes').text(summary.summary.nodes ?? '-');
        $('#tm-links').text(summary.summary.links ?? '-');

        if (geomap && geomap.status === 'ok' && Array.isArray(geomap.points)) {
            $('#tm-geo-points').text(geomap.points.length);
        } else {
            $('#tm-geo-points').text('0');
        }
    }
}
