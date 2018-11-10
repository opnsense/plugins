/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./src/nginx_config.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./src/controller/KeyValueMapField.js":
/*!********************************************!*\
  !*** ./src/controller/KeyValueMapField.js ***!
  \********************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony default export */ __webpack_exports__[\"default\"] = (Backbone.View.extend({\n    tagName: 'div',\n    attributes: {'class': 'container-fluid'},\n    child_views: [],\n    createModel: null,\n    upstreamCollection: null,\n    initialize: function (params) {\n        this.dataField = $(params.dataField);\n        this.entryclass = params.entryclass;\n        this.createModel = params.createModel;\n        this.upstreamCollection = params.upstreamCollection;\n        this.listenTo(this.collection, \"add remove reset\", this.render);\n        this.listenTo(this.collection, \"change\", this.update);\n        // inject our table holder\n        this.dataField.after(this.$el);\n    },\n    events: {\n        \"click .add\": \"addEntry\"\n    },\n    render: function () {\n        // clear table\n        this.child_views.forEach((model) => model.remove());\n        this.$el.html('');\n        this.child_views = [];\n        this.update();\n        this.collection.each((model) => {\n            const childView = new this.entryclass({\n                model: model,\n                collection: this.collection,\n                upstreamCollection: this.upstreamCollection\n            });\n            this.child_views.push(childView);\n            this.$el.append(childView.$el);\n            childView.render();\n        });\n        this.$el.append($(`\n                <div class=\"row\">\n                    <button class=\"btn btn-primary pull-right add\">\n                        <span class=\"fa fa-plus\"></span>\n                    </button>\n                </div>`));\n    },\n    update: function () {\n        this.dataField.val(JSON.stringify(this.collection.toJSON()));\n    },\n    addEntry: function (e) {\n        e.preventDefault();\n        this.collection.add(this.createModel());\n    }\n}));\n\n//# sourceURL=webpack:///./src/controller/KeyValueMapField.js?");

/***/ }),

/***/ "./src/controller/KeyValueMapFieldEntry.js":
/*!*************************************************!*\
  !*** ./src/controller/KeyValueMapFieldEntry.js ***!
  \*************************************************/
/*! exports provided: KeyValueMapFieldEntryUpstreamMap, KeyValueMapFieldEntryACL */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, \"KeyValueMapFieldEntryUpstreamMap\", function() { return KeyValueMapFieldEntryUpstreamMap; });\n/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, \"KeyValueMapFieldEntryACL\", function() { return KeyValueMapFieldEntryACL; });\nconst KeyValueMapFieldEntryUpstreamMap = Backbone.View.extend({\n\n    tagName: 'div',\n    attributes: {'class': 'row'},\n    events: {\n        'keyup .key': function () {\n            this.model.set('hostname', this.key.value);\n        },\n        'change .value': function () {\n            this.model.set('upstream', this.value.value);\n        },\n        \"click .delete\" : \"deleteEntry\"\n    },\n    key: null,\n    value: null,\n    delBtn: null,\n    first: null,\n    second: null,\n    third: null,\n    upstreamCollection: null,\n    initialize: function (params) {\n        this.upstreamCollection = params.upstreamCollection;\n        this.listenTo(this.upstreamCollection, \"update reset add remove\", this.regenerate_list);\n        this.first = document.createElement('div');\n        this.first.classList.add('col-sm-5');\n        this.key = document.createElement('input');\n        this.first.append(this.key);\n        this.key.type = 'text';\n        this.key.classList.add('key');\n        this.key.value = this.model.get('hostname');\n\n        this.second = document.createElement('div');\n        this.second.classList.add('col-sm-5');\n        this.value = document.createElement('select');\n        this.second.append(this.value);\n        this.value.classList.add('value');\n        this.value.classList.add('form-control');\n        this.value.value = this.model.get('upstream');\n\n        this.third = document.createElement('div');\n        this.third.classList.add('col-sm-2');\n        this.third.style.textAlign = 'right';\n        this.delBtn = document.createElement(\"button\");\n        this.delBtn.classList.add('delete');\n        this.delBtn.classList.add('btn');\n        this.delBtn.innerHTML = '<span class=\"fa fa-trash\"></span>';\n        this.third.append(this.delBtn);\n        if (!this.model.has('upstream') ||\n            this.upstreamCollection.where ({'uuid' : this.model.get('upstream')}).length === 0) {\n            if (this.upstreamCollection.length > 0) {\n                this.model.set('upstream', this.upstreamCollection.at(0).get('uuid'));\n            }\n        }\n\n\n        this.$el.append(this.first).append(this.second).append(this.third);\n    },\n    render: function() {\n        $(this.key).val(this.model.get('hostname'));\n        this.regenerate_list();\n        $(this.value).val(this.model.get('upstream'));\n    },\n    deleteEntry: function (e) {\n        e.preventDefault();\n        this.collection.remove(this.model);\n    },\n    regenerate_list: function () {\n        // backup value\n        const v = $(this.value);\n        // clear the dropdown\n        v.html('');\n        this.upstreamCollection.each(\n            (mdl) => v.append(`<option value=\"${mdl.escape('uuid')}\">${mdl.escape('description')}</option>`)\n        );\n        // restore\n        v.val(this.model.get('upstream'));\n        v.selectpicker('refresh');\n    }\n});\nconst KeyValueMapFieldEntryACL = Backbone.View.extend({\n\n    tagName: 'div',\n    attributes: {'class': 'row'},\n    events: {\n        'keyup .key': function () {\n            this.model.set('network', this.key.value);\n        },\n        'change .value': function () {\n            this.model.set('action', this.value.value);\n        },\n        \"click .delete\" : \"deleteEntry\"\n    },\n    key: null,\n    value: null,\n    delBtn: null,\n    first: null,\n    second: null,\n    third: null,\n    upstreamCollection: null,\n    initialize: function (params) {\n        this.upstreamCollection = params.upstreamCollection;\n        this.listenTo(this.upstreamCollection, \"update reset add remove\", this.regenerate_list);\n        this.first = document.createElement('div');\n        this.first.classList.add('col-sm-5');\n        this.key = document.createElement('input');\n        this.first.append(this.key);\n        this.key.type = 'text';\n        this.key.classList.add('key');\n        this.key.value = this.model.get('network');\n\n        this.second = document.createElement('div');\n        this.second.classList.add('col-sm-5');\n        this.value = document.createElement('select');\n        this.second.append(this.value);\n        this.value.classList.add('value');\n        this.value.classList.add('form-control');\n        this.value.value = this.model.get('action');\n\n        this.third = document.createElement('div');\n        this.third.classList.add('col-sm-2');\n        this.third.style.textAlign = 'right';\n        this.delBtn = document.createElement(\"button\");\n        this.delBtn.classList.add('delete');\n        this.delBtn.classList.add('btn');\n        this.delBtn.innerHTML = '<span class=\"fa fa-trash\"></span>';\n        this.third.append(this.delBtn);\n\n\n        this.$el.append(this.first).append(this.second).append(this.third);\n    },\n    render: function() {\n        $(this.key).val(this.model.get('network'));\n        this.regenerate_list();\n        $(this.value).val(this.model.get('action'));\n    },\n    deleteEntry: function (e) {\n        e.preventDefault();\n        this.collection.remove(this.model);\n    },\n    regenerate_list: function () {\n        // backup value\n        const v = $(this.value);\n        // clear the dropdown\n        v.html('');\n        this.upstreamCollection.each(\n            (mdl) => v.append(`<option value=\"${mdl.escape('value')}\">${mdl.escape('name')}</option>`)\n        );\n        // restore\n        v.val(this.model.get('action'));\n        v.selectpicker('refresh');\n    }\n});\n\n\n//# sourceURL=webpack:///./src/controller/KeyValueMapFieldEntry.js?");

/***/ }),

/***/ "./src/models/IPACLCollection.js":
/*!***************************************!*\
  !*** ./src/models/IPACLCollection.js ***!
  \***************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony default export */ __webpack_exports__[\"default\"] = (Backbone.Collection.extend({\n    initialize: function() {\n        let that = this;\n        $('#ipacl\\\\.data').change(function () {\n            that.regenerateFromView();\n        });\n    },\n    regenerateFromView: function () {\n        let data = JSON.parse($('#ipacl\\\\.data').val());\n        if (!_.isArray(data)) {\n            data = [];\n        }\n        this.reset(data);\n    }\n}));\n\n//# sourceURL=webpack:///./src/models/IPACLCollection.js?");

/***/ }),

/***/ "./src/models/IPACLModel.js":
/*!**********************************!*\
  !*** ./src/models/IPACLModel.js ***!
  \**********************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony default export */ __webpack_exports__[\"default\"] = (Backbone.Model.extend({\n    // standard model\n}));\n\n//# sourceURL=webpack:///./src/models/IPACLModel.js?");

/***/ }),

/***/ "./src/models/SNIHostnameUpstreamCollection.js":
/*!*****************************************************!*\
  !*** ./src/models/SNIHostnameUpstreamCollection.js ***!
  \*****************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony default export */ __webpack_exports__[\"default\"] = (Backbone.Collection.extend({\n    initialize: function() {\n        let that = this;\n        $('#snihostname\\\\.data').change(function () {\n            that.regenerateFromView();\n        });\n    },\n    regenerateFromView: function () {\n        let data = JSON.parse($('#snihostname\\\\.data').val());\n        if (!_.isArray(data)) {\n            data = [];\n        }\n        this.reset(data);\n    }\n}));\n\n//# sourceURL=webpack:///./src/models/SNIHostnameUpstreamCollection.js?");

/***/ }),

/***/ "./src/models/SNIHostnameUpstreamModel.js":
/*!************************************************!*\
  !*** ./src/models/SNIHostnameUpstreamModel.js ***!
  \************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony default export */ __webpack_exports__[\"default\"] = (Backbone.Model.extend({\n    // standard model\n}));\n\n//# sourceURL=webpack:///./src/models/SNIHostnameUpstreamModel.js?");

/***/ }),

/***/ "./src/models/UpstreamCollection.js":
/*!******************************************!*\
  !*** ./src/models/UpstreamCollection.js ***!
  \******************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\nconst UpstreamCollection = Backbone.Collection.extend({\n    url: '/api/nginx/settings/searchupstream',\n    parse: function(response) {\n        return response.rows;\n    }\n});\n/* harmony default export */ __webpack_exports__[\"default\"] = (UpstreamCollection);\n\n//# sourceURL=webpack:///./src/models/UpstreamCollection.js?");

/***/ }),

/***/ "./src/nginx_config.js":
/*!*****************************!*\
  !*** ./src/nginx_config.js ***!
  \*****************************/
/*! no exports provided */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _controller_KeyValueMapField__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./controller/KeyValueMapField */ \"./src/controller/KeyValueMapField.js\");\n/* harmony import */ var _models_UpstreamCollection__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./models/UpstreamCollection */ \"./src/models/UpstreamCollection.js\");\n/* harmony import */ var _controller_KeyValueMapFieldEntry__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./controller/KeyValueMapFieldEntry */ \"./src/controller/KeyValueMapFieldEntry.js\");\n/* harmony import */ var _models_SNIHostnameUpstreamCollection__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./models/SNIHostnameUpstreamCollection */ \"./src/models/SNIHostnameUpstreamCollection.js\");\n/* harmony import */ var _models_SNIHostnameUpstreamModel__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./models/SNIHostnameUpstreamModel */ \"./src/models/SNIHostnameUpstreamModel.js\");\n/* harmony import */ var _models_IPACLModel__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./models/IPACLModel */ \"./src/models/IPACLModel.js\");\n/* harmony import */ var _models_IPACLCollection__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./models/IPACLCollection */ \"./src/models/IPACLCollection.js\");\n\n\n\n\n\n\n\n\nconst uc = new _models_UpstreamCollection__WEBPACK_IMPORTED_MODULE_1__[\"default\"]();\nconst actioncollection = new Backbone.Collection([\n    {\n        'name': 'Deny',\n        'value': 'deny'\n    },\n    {\n        'name': 'Allow',\n        'value': 'allow'\n    }\n]);\n\nfunction bind_save_buttons() {\n// form save event handlers for all defined forms\n    $('[id*=\"save_\"]').each(function () {\n        $(this).click(function (event) {\n            let frm_id = $(this).closest(\"form\").attr(\"id\");\n            let frm_title = $(this).closest(\"form\").attr(\"data-title\");\n            // save data for General TAB\n            saveFormToEndpoint(url = \"/api/nginx/settings/set\", formid = frm_id, callback_ok = function () {\n                // on correct save, perform reconfigure. set progress animation when reloading\n                $(\"#\" + frm_id + \"_progress\").addClass(\"fa fa-spinner fa-pulse\");\n\n                ajaxCall(url = \"/api/nginx/service/reconfigure\", sendData = {}, callback = function (data, status) {\n                    // when done, disable progress animation.\n                    $(\"#\" + frm_id + \"_progress\").removeClass(\"fa fa-spinner fa-pulse\");\n\n                    if (data !== undefined && (status !== \"success\" || data['status'] !== 'ok')) {\n                        // fix error handling\n                        BootstrapDialog.show({\n                            type: BootstrapDialog.TYPE_WARNING,\n                            title: frm_title,\n                            message: JSON.stringify(data),\n                            draggable: true\n                        });\n                    } else {\n                        updateServiceControlUI('nginx');\n                    }\n                });\n            });\n        });\n    });\n}\n\nfunction init_grids() {\n    ['upstream',\n        'upstreamserver',\n        'location',\n        'credential',\n        'userlist',\n        'httpserver',\n        'streamserver',\n        'httprewrite',\n        'custompolicy',\n        'security_header',\n        'ipacl',\n        'limit_zone',\n        'cache_path',\n        'limit_request_connection',\n        'snifwd',\n        'naxsirule'].forEach(function (element) {\n        $(\"#grid-\" + element).UIBootgrid(\n            {\n                'search': '/api/nginx/settings/search' + element,\n                'get': '/api/nginx/settings/get' + element + '/',\n                'set': '/api/nginx/settings/set' + element + '/',\n                'add': '/api/nginx/settings/add' + element + '/',\n                'del': '/api/nginx/settings/del' + element + '/',\n                'options': {selection: false, multiSelect: false}\n            }\n        );\n    });\n}\n\nfunction initSNIFieldComponent() {\n    let snifield = new _controller_KeyValueMapField__WEBPACK_IMPORTED_MODULE_0__[\"default\"]({\n        dataField: document.getElementById('snihostname.data'),\n        upstreamCollection: uc,\n        entryclass: _controller_KeyValueMapFieldEntry__WEBPACK_IMPORTED_MODULE_2__[\"KeyValueMapFieldEntryUpstreamMap\"],\n        collection: new _models_SNIHostnameUpstreamCollection__WEBPACK_IMPORTED_MODULE_3__[\"default\"](),\n        createModel: function () {\n            return new _models_SNIHostnameUpstreamModel__WEBPACK_IMPORTED_MODULE_4__[\"default\"]({\n                hostname: 'localhost',\n            });\n        }\n    });\n    window.snifield = snifield;\n    snifield.render();\n    $(\"#grid-upstream\").on(\"loaded.rs.jquery.bootgrid\", function () {\n        /* we always have to reload too after bootgrid reloads */\n        uc.fetch();\n    });\n    uc.fetch();\n}\n\n$( document ).ready(function() {\n\n    let data_get_map = {'frm_nginx':'/api/nginx/settings/get'};\n\n    // load initial data\n    mapDataToFormUI(data_get_map).done(function(){\n        formatTokenizersUI();\n        $('select[data-allownew=\"false\"]').selectpicker('refresh');\n        updateServiceControlUI('nginx');\n    });\n\n    // update history on tab state and implement navigation\n    if(window.location.hash !== \"\") {\n        $('a[href=\"' + window.location.hash + '\"]').click()\n    }\n    $('.nav-tabs a').on('shown.bs.tab', function (e) {\n        history.pushState(null, null, e.target.hash);\n    });\n\n    $('.reload_btn').click(function() {\n        $(\".reloadAct_progress\").addClass(\"fa-spin\");\n        ajaxCall(url=\"/api/nginx/service/reconfigure\", sendData={}, callback=function(data,status) {\n            $(\".reloadAct_progress\").removeClass(\"fa-spin\");\n        });\n    });\n\n\n    bind_save_buttons();\n    init_grids();\n    bind_naxsi_rule_dl_button();\n    initSNIFieldComponent();\n    let ipaclfield = new _controller_KeyValueMapField__WEBPACK_IMPORTED_MODULE_0__[\"default\"]({\n        dataField: document.getElementById('ipacl.data'),\n        upstreamCollection: actioncollection,\n        entryclass: _controller_KeyValueMapFieldEntry__WEBPACK_IMPORTED_MODULE_2__[\"KeyValueMapFieldEntryACL\"],\n        collection: new _models_IPACLCollection__WEBPACK_IMPORTED_MODULE_6__[\"default\"](),\n        createModel: function () {\n            return new _models_IPACLModel__WEBPACK_IMPORTED_MODULE_5__[\"default\"]({\n                network: '::',\n                action: 'deny'\n            });\n        }\n    });\n    window.ipaclfield = ipaclfield;\n    ipaclfield.render();\n});\n\n\n//# sourceURL=webpack:///./src/nginx_config.js?");

/***/ })

/******/ });