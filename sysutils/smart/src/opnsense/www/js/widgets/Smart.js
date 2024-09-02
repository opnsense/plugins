export default class Smart extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 300;
        this.disks = null;
    }

    getMarkup() {
        const $container = $('<div></div>');
        const $smarttable = this.createTable('smart-table', {
            headerPosition: 'left',
        });
        $container.append($smarttable);
        return $container;
    }

    async onWidgetTick() {
        if (this.disks && this.disks.devices) {
            for (const device of this.disks.devices) {
                try {
                    const data = await ajaxCall("/api/smart/service/info", { type: "H", device });
                    const health = data.output.includes("PASSED");
                    $(`#${device}`).css({ color: health ? "green" : "red", fontSize: '150%' });
                    $(`#${device}`).text(health ? "OK" : "FAILED");
                } catch (error) {
                    super.updateTable('smart-table', [[["Error"], $(`<span>${this.translations.nosmart} ${device}: ${error}</span>`).prop('outerHTML')]]);
                }
            }
        }
    }

async onMarkupRendered() {
        try {
            this.disks = await ajaxCall("/api/smart/service/list", {});
            const rows = [];
            for (const device of this.disks.devices) {
                const field = $(`<span id="${device}">`).prop('outerHTML');
                rows.push([[device], field]);
            }
            super.updateTable('smart-table', rows);
        } catch (error) {
            super.updateTable('smart-table', [[["Error"], $(`<span>${this.translations.nodisk}: ${error}</span>`).prop('outerHTML')]]);
        }
    }
}
