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
/******/ 	return __webpack_require__(__webpack_require__.s = "./src/logviewer.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./node_modules/lodash/_Symbol.js":
/*!****************************************!*\
  !*** ./node_modules/lodash/_Symbol.js ***!
  \****************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var root = __webpack_require__(/*! ./_root */ \"./node_modules/lodash/_root.js\");\n\n/** Built-in value references. */\nvar Symbol = root.Symbol;\n\nmodule.exports = Symbol;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/_Symbol.js?");

/***/ }),

/***/ "./node_modules/lodash/_arrayMap.js":
/*!******************************************!*\
  !*** ./node_modules/lodash/_arrayMap.js ***!
  \******************************************/
/*! no static exports found */
/***/ (function(module, exports) {

eval("/**\n * A specialized version of `_.map` for arrays without support for iteratee\n * shorthands.\n *\n * @private\n * @param {Array} [array] The array to iterate over.\n * @param {Function} iteratee The function invoked per iteration.\n * @returns {Array} Returns the new mapped array.\n */\nfunction arrayMap(array, iteratee) {\n  var index = -1,\n      length = array == null ? 0 : array.length,\n      result = Array(length);\n\n  while (++index < length) {\n    result[index] = iteratee(array[index], index, array);\n  }\n  return result;\n}\n\nmodule.exports = arrayMap;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/_arrayMap.js?");

/***/ }),

/***/ "./node_modules/lodash/_baseGetTag.js":
/*!********************************************!*\
  !*** ./node_modules/lodash/_baseGetTag.js ***!
  \********************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var Symbol = __webpack_require__(/*! ./_Symbol */ \"./node_modules/lodash/_Symbol.js\"),\n    getRawTag = __webpack_require__(/*! ./_getRawTag */ \"./node_modules/lodash/_getRawTag.js\"),\n    objectToString = __webpack_require__(/*! ./_objectToString */ \"./node_modules/lodash/_objectToString.js\");\n\n/** `Object#toString` result references. */\nvar nullTag = '[object Null]',\n    undefinedTag = '[object Undefined]';\n\n/** Built-in value references. */\nvar symToStringTag = Symbol ? Symbol.toStringTag : undefined;\n\n/**\n * The base implementation of `getTag` without fallbacks for buggy environments.\n *\n * @private\n * @param {*} value The value to query.\n * @returns {string} Returns the `toStringTag`.\n */\nfunction baseGetTag(value) {\n  if (value == null) {\n    return value === undefined ? undefinedTag : nullTag;\n  }\n  return (symToStringTag && symToStringTag in Object(value))\n    ? getRawTag(value)\n    : objectToString(value);\n}\n\nmodule.exports = baseGetTag;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/_baseGetTag.js?");

/***/ }),

/***/ "./node_modules/lodash/_basePropertyOf.js":
/*!************************************************!*\
  !*** ./node_modules/lodash/_basePropertyOf.js ***!
  \************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

eval("/**\n * The base implementation of `_.propertyOf` without support for deep paths.\n *\n * @private\n * @param {Object} object The object to query.\n * @returns {Function} Returns the new accessor function.\n */\nfunction basePropertyOf(object) {\n  return function(key) {\n    return object == null ? undefined : object[key];\n  };\n}\n\nmodule.exports = basePropertyOf;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/_basePropertyOf.js?");

/***/ }),

/***/ "./node_modules/lodash/_baseToString.js":
/*!**********************************************!*\
  !*** ./node_modules/lodash/_baseToString.js ***!
  \**********************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var Symbol = __webpack_require__(/*! ./_Symbol */ \"./node_modules/lodash/_Symbol.js\"),\n    arrayMap = __webpack_require__(/*! ./_arrayMap */ \"./node_modules/lodash/_arrayMap.js\"),\n    isArray = __webpack_require__(/*! ./isArray */ \"./node_modules/lodash/isArray.js\"),\n    isSymbol = __webpack_require__(/*! ./isSymbol */ \"./node_modules/lodash/isSymbol.js\");\n\n/** Used as references for various `Number` constants. */\nvar INFINITY = 1 / 0;\n\n/** Used to convert symbols to primitives and strings. */\nvar symbolProto = Symbol ? Symbol.prototype : undefined,\n    symbolToString = symbolProto ? symbolProto.toString : undefined;\n\n/**\n * The base implementation of `_.toString` which doesn't convert nullish\n * values to empty strings.\n *\n * @private\n * @param {*} value The value to process.\n * @returns {string} Returns the string.\n */\nfunction baseToString(value) {\n  // Exit early for strings to avoid a performance hit in some environments.\n  if (typeof value == 'string') {\n    return value;\n  }\n  if (isArray(value)) {\n    // Recursively convert values (susceptible to call stack limits).\n    return arrayMap(value, baseToString) + '';\n  }\n  if (isSymbol(value)) {\n    return symbolToString ? symbolToString.call(value) : '';\n  }\n  var result = (value + '');\n  return (result == '0' && (1 / value) == -INFINITY) ? '-0' : result;\n}\n\nmodule.exports = baseToString;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/_baseToString.js?");

/***/ }),

/***/ "./node_modules/lodash/_escapeHtmlChar.js":
/*!************************************************!*\
  !*** ./node_modules/lodash/_escapeHtmlChar.js ***!
  \************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var basePropertyOf = __webpack_require__(/*! ./_basePropertyOf */ \"./node_modules/lodash/_basePropertyOf.js\");\n\n/** Used to map characters to HTML entities. */\nvar htmlEscapes = {\n  '&': '&amp;',\n  '<': '&lt;',\n  '>': '&gt;',\n  '\"': '&quot;',\n  \"'\": '&#39;'\n};\n\n/**\n * Used by `_.escape` to convert characters to HTML entities.\n *\n * @private\n * @param {string} chr The matched character to escape.\n * @returns {string} Returns the escaped character.\n */\nvar escapeHtmlChar = basePropertyOf(htmlEscapes);\n\nmodule.exports = escapeHtmlChar;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/_escapeHtmlChar.js?");

/***/ }),

/***/ "./node_modules/lodash/_freeGlobal.js":
/*!********************************************!*\
  !*** ./node_modules/lodash/_freeGlobal.js ***!
  \********************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("/* WEBPACK VAR INJECTION */(function(global) {/** Detect free variable `global` from Node.js. */\nvar freeGlobal = typeof global == 'object' && global && global.Object === Object && global;\n\nmodule.exports = freeGlobal;\n\n/* WEBPACK VAR INJECTION */}.call(this, __webpack_require__(/*! ./../webpack/buildin/global.js */ \"./node_modules/webpack/buildin/global.js\")))\n\n//# sourceURL=webpack:///./node_modules/lodash/_freeGlobal.js?");

/***/ }),

/***/ "./node_modules/lodash/_getRawTag.js":
/*!*******************************************!*\
  !*** ./node_modules/lodash/_getRawTag.js ***!
  \*******************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var Symbol = __webpack_require__(/*! ./_Symbol */ \"./node_modules/lodash/_Symbol.js\");\n\n/** Used for built-in method references. */\nvar objectProto = Object.prototype;\n\n/** Used to check objects for own properties. */\nvar hasOwnProperty = objectProto.hasOwnProperty;\n\n/**\n * Used to resolve the\n * [`toStringTag`](http://ecma-international.org/ecma-262/7.0/#sec-object.prototype.tostring)\n * of values.\n */\nvar nativeObjectToString = objectProto.toString;\n\n/** Built-in value references. */\nvar symToStringTag = Symbol ? Symbol.toStringTag : undefined;\n\n/**\n * A specialized version of `baseGetTag` which ignores `Symbol.toStringTag` values.\n *\n * @private\n * @param {*} value The value to query.\n * @returns {string} Returns the raw `toStringTag`.\n */\nfunction getRawTag(value) {\n  var isOwn = hasOwnProperty.call(value, symToStringTag),\n      tag = value[symToStringTag];\n\n  try {\n    value[symToStringTag] = undefined;\n    var unmasked = true;\n  } catch (e) {}\n\n  var result = nativeObjectToString.call(value);\n  if (unmasked) {\n    if (isOwn) {\n      value[symToStringTag] = tag;\n    } else {\n      delete value[symToStringTag];\n    }\n  }\n  return result;\n}\n\nmodule.exports = getRawTag;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/_getRawTag.js?");

/***/ }),

/***/ "./node_modules/lodash/_objectToString.js":
/*!************************************************!*\
  !*** ./node_modules/lodash/_objectToString.js ***!
  \************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

eval("/** Used for built-in method references. */\nvar objectProto = Object.prototype;\n\n/**\n * Used to resolve the\n * [`toStringTag`](http://ecma-international.org/ecma-262/7.0/#sec-object.prototype.tostring)\n * of values.\n */\nvar nativeObjectToString = objectProto.toString;\n\n/**\n * Converts `value` to a string using `Object.prototype.toString`.\n *\n * @private\n * @param {*} value The value to convert.\n * @returns {string} Returns the converted string.\n */\nfunction objectToString(value) {\n  return nativeObjectToString.call(value);\n}\n\nmodule.exports = objectToString;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/_objectToString.js?");

/***/ }),

/***/ "./node_modules/lodash/_root.js":
/*!**************************************!*\
  !*** ./node_modules/lodash/_root.js ***!
  \**************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var freeGlobal = __webpack_require__(/*! ./_freeGlobal */ \"./node_modules/lodash/_freeGlobal.js\");\n\n/** Detect free variable `self`. */\nvar freeSelf = typeof self == 'object' && self && self.Object === Object && self;\n\n/** Used as a reference to the global object. */\nvar root = freeGlobal || freeSelf || Function('return this')();\n\nmodule.exports = root;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/_root.js?");

/***/ }),

/***/ "./node_modules/lodash/escape.js":
/*!***************************************!*\
  !*** ./node_modules/lodash/escape.js ***!
  \***************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var escapeHtmlChar = __webpack_require__(/*! ./_escapeHtmlChar */ \"./node_modules/lodash/_escapeHtmlChar.js\"),\n    toString = __webpack_require__(/*! ./toString */ \"./node_modules/lodash/toString.js\");\n\n/** Used to match HTML entities and HTML characters. */\nvar reUnescapedHtml = /[&<>\"']/g,\n    reHasUnescapedHtml = RegExp(reUnescapedHtml.source);\n\n/**\n * Converts the characters \"&\", \"<\", \">\", '\"', and \"'\" in `string` to their\n * corresponding HTML entities.\n *\n * **Note:** No other characters are escaped. To escape additional\n * characters use a third-party library like [_he_](https://mths.be/he).\n *\n * Though the \">\" character is escaped for symmetry, characters like\n * \">\" and \"/\" don't need escaping in HTML and have no special meaning\n * unless they're part of a tag or unquoted attribute value. See\n * [Mathias Bynens's article](https://mathiasbynens.be/notes/ambiguous-ampersands)\n * (under \"semi-related fun fact\") for more details.\n *\n * When working with HTML you should always\n * [quote attribute values](http://wonko.com/post/html-escaping) to reduce\n * XSS vectors.\n *\n * @static\n * @since 0.1.0\n * @memberOf _\n * @category String\n * @param {string} [string=''] The string to escape.\n * @returns {string} Returns the escaped string.\n * @example\n *\n * _.escape('fred, barney, & pebbles');\n * // => 'fred, barney, &amp; pebbles'\n */\nfunction escape(string) {\n  string = toString(string);\n  return (string && reHasUnescapedHtml.test(string))\n    ? string.replace(reUnescapedHtml, escapeHtmlChar)\n    : string;\n}\n\nmodule.exports = escape;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/escape.js?");

/***/ }),

/***/ "./node_modules/lodash/isArray.js":
/*!****************************************!*\
  !*** ./node_modules/lodash/isArray.js ***!
  \****************************************/
/*! no static exports found */
/***/ (function(module, exports) {

eval("/**\n * Checks if `value` is classified as an `Array` object.\n *\n * @static\n * @memberOf _\n * @since 0.1.0\n * @category Lang\n * @param {*} value The value to check.\n * @returns {boolean} Returns `true` if `value` is an array, else `false`.\n * @example\n *\n * _.isArray([1, 2, 3]);\n * // => true\n *\n * _.isArray(document.body.children);\n * // => false\n *\n * _.isArray('abc');\n * // => false\n *\n * _.isArray(_.noop);\n * // => false\n */\nvar isArray = Array.isArray;\n\nmodule.exports = isArray;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/isArray.js?");

/***/ }),

/***/ "./node_modules/lodash/isObjectLike.js":
/*!*********************************************!*\
  !*** ./node_modules/lodash/isObjectLike.js ***!
  \*********************************************/
/*! no static exports found */
/***/ (function(module, exports) {

eval("/**\n * Checks if `value` is object-like. A value is object-like if it's not `null`\n * and has a `typeof` result of \"object\".\n *\n * @static\n * @memberOf _\n * @since 4.0.0\n * @category Lang\n * @param {*} value The value to check.\n * @returns {boolean} Returns `true` if `value` is object-like, else `false`.\n * @example\n *\n * _.isObjectLike({});\n * // => true\n *\n * _.isObjectLike([1, 2, 3]);\n * // => true\n *\n * _.isObjectLike(_.noop);\n * // => false\n *\n * _.isObjectLike(null);\n * // => false\n */\nfunction isObjectLike(value) {\n  return value != null && typeof value == 'object';\n}\n\nmodule.exports = isObjectLike;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/isObjectLike.js?");

/***/ }),

/***/ "./node_modules/lodash/isSymbol.js":
/*!*****************************************!*\
  !*** ./node_modules/lodash/isSymbol.js ***!
  \*****************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var baseGetTag = __webpack_require__(/*! ./_baseGetTag */ \"./node_modules/lodash/_baseGetTag.js\"),\n    isObjectLike = __webpack_require__(/*! ./isObjectLike */ \"./node_modules/lodash/isObjectLike.js\");\n\n/** `Object#toString` result references. */\nvar symbolTag = '[object Symbol]';\n\n/**\n * Checks if `value` is classified as a `Symbol` primitive or object.\n *\n * @static\n * @memberOf _\n * @since 4.0.0\n * @category Lang\n * @param {*} value The value to check.\n * @returns {boolean} Returns `true` if `value` is a symbol, else `false`.\n * @example\n *\n * _.isSymbol(Symbol.iterator);\n * // => true\n *\n * _.isSymbol('abc');\n * // => false\n */\nfunction isSymbol(value) {\n  return typeof value == 'symbol' ||\n    (isObjectLike(value) && baseGetTag(value) == symbolTag);\n}\n\nmodule.exports = isSymbol;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/isSymbol.js?");

/***/ }),

/***/ "./node_modules/lodash/toString.js":
/*!*****************************************!*\
  !*** ./node_modules/lodash/toString.js ***!
  \*****************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var baseToString = __webpack_require__(/*! ./_baseToString */ \"./node_modules/lodash/_baseToString.js\");\n\n/**\n * Converts `value` to a string. An empty string is returned for `null`\n * and `undefined` values. The sign of `-0` is preserved.\n *\n * @static\n * @memberOf _\n * @since 4.0.0\n * @category Lang\n * @param {*} value The value to convert.\n * @returns {string} Returns the converted string.\n * @example\n *\n * _.toString(null);\n * // => ''\n *\n * _.toString(-0);\n * // => '-0'\n *\n * _.toString([1, 2, 3]);\n * // => '1,2,3'\n */\nfunction toString(value) {\n  return value == null ? '' : baseToString(value);\n}\n\nmodule.exports = toString;\n\n\n//# sourceURL=webpack:///./node_modules/lodash/toString.js?");

/***/ }),

/***/ "./node_modules/webpack/buildin/global.js":
/*!***********************************!*\
  !*** (webpack)/buildin/global.js ***!
  \***********************************/
/*! no static exports found */
/***/ (function(module, exports) {

eval("var g;\n\n// This works in non-strict mode\ng = (function() {\n\treturn this;\n})();\n\ntry {\n\t// This works if eval is allowed (see CSP)\n\tg = g || Function(\"return this\")() || (1, eval)(\"this\");\n} catch (e) {\n\t// This works if the window reference is available\n\tif (typeof window === \"object\") g = window;\n}\n\n// g can still be undefined, but nothing to do about it...\n// We return undefined, instead of nothing here, so it's\n// easier to handle this case. if(!global) { ...}\n\nmodule.exports = g;\n\n\n//# sourceURL=webpack:///(webpack)/buildin/global.js?");

/***/ }),

/***/ "./src/config.js":
/*!***********************!*\
  !*** ./src/config.js ***!
  \***********************/
/*! exports provided: defaultEndpoints */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, \"defaultEndpoints\", function() { return defaultEndpoints; });\nconst defaultEndpoints = new Backbone.Collection([\n    {\n        \"name\": 'Access Logs',\n        \"logType\" : 'accesses'\n    },\n    {\n        \"name\": 'Error Logs',\n        \"logType\" : 'errors'\n    }\n]);\n\n\n//# sourceURL=webpack:///./src/config.js?");

/***/ }),

/***/ "./src/controller/LogCategoryList.js":
/*!*******************************************!*\
  !*** ./src/controller/LogCategoryList.js ***!
  \*******************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _models_LogCollection__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../models/LogCollection */ \"./src/models/LogCollection.js\");\n/* harmony import */ var _TabLogList__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./TabLogList */ \"./src/controller/TabLogList.js\");\n\n\n\nlet LogCategoryList = Backbone.View.extend({\n    tagName: \"ul\",\n    className: \"nav nav-tabs\",\n\n    initialize: function(data) {\n        this.listenTo(this.collection, \"sync\",   this.render);\n        this.listenTo(this.collection, \"update\", this.render);\n        this.logview = data.logview;\n    },\n\n    render: function() {\n        this.$el.attr('role', 'tablist');\n        this.$el.html('');\n        this.collection.forEach((element) => this.render_one(element));\n    },\n\n    render_one: function(element) {\n        const servers = new _models_LogCollection__WEBPACK_IMPORTED_MODULE_0__[\"default\"](\n            {\n                uuid: element.get('url'),\n                logType: element.get('logType')\n            }\n        );\n        const logList = new _TabLogList__WEBPACK_IMPORTED_MODULE_1__[\"default\"]({\n            collection: servers,\n            model: element,\n            logview: this.logview\n        });\n        this.$el.append(logList.$el);\n        servers.fetch();\n    }\n});\n\n/* harmony default export */ __webpack_exports__[\"default\"] = (LogCategoryList);\n\n//# sourceURL=webpack:///./src/controller/LogCategoryList.js?");

/***/ }),

/***/ "./src/controller/LogView.js":
/*!***********************************!*\
  !*** ./src/controller/LogView.js ***!
  \***********************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _templates_AccessLogLine_html__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../templates/AccessLogLine.html */ \"./src/templates/AccessLogLine.html\");\n/* harmony import */ var _templates_AccessLogLine_html__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_templates_AccessLogLine_html__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _templates_ErrorLogLine_html__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../templates/ErrorLogLine.html */ \"./src/templates/ErrorLogLine.html\");\n/* harmony import */ var _templates_ErrorLogLine_html__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_templates_ErrorLogLine_html__WEBPACK_IMPORTED_MODULE_1__);\n/* harmony import */ var _templates_logviewer_html__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../templates/logviewer.html */ \"./src/templates/logviewer.html\");\n/* harmony import */ var _templates_logviewer_html__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_templates_logviewer_html__WEBPACK_IMPORTED_MODULE_2__);\n/* harmony import */ var _models_LogLinesCollection__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../models/LogLinesCollection */ \"./src/models/LogLinesCollection.js\");\n\n\n\n\n\nconst LogView = Backbone.View.extend({\n\n    tagName: 'div',\n    className: 'content-box tab-content',\n    events: {\n        \"click .mainentry\": \"mainMenuClick\"\n    },\n\n    initialize: function() {\n        this.collection = new _models_LogLinesCollection__WEBPACK_IMPORTED_MODULE_3__[\"default\"]();\n        this.listenTo(this.collection, \"sync\", this.render);\n        this.listenTo(this.collection, \"update\", this.render);\n        this.type = '';\n    },\n\n    render: function() {\n        this.$el.html('');\n        let table_content = '';\n        let template = this.get_template();\n        this.collection.forEach((model) => table_content += template({model: model}));\n        this.$el.html(_templates_logviewer_html__WEBPACK_IMPORTED_MODULE_2___default()({table_body: table_content, log_type: this.type}));\n    },\n    get_template: function() {\n        if (this.type === 'accesses') {\n            return _templates_AccessLogLine_html__WEBPACK_IMPORTED_MODULE_0___default.a;\n        } else {\n            return _templates_ErrorLogLine_html__WEBPACK_IMPORTED_MODULE_1___default.a;\n        }\n    },\n    get_log: function(type, uuid) {\n        this.collection.uuid = uuid;\n        this.collection.logType = type;\n        this.type = type;\n        this.update();\n    },\n    update: function () {\n        this.collection.fetch();\n    }\n});\n/* harmony default export */ __webpack_exports__[\"default\"] = (LogView);\n\n//# sourceURL=webpack:///./src/controller/LogView.js?");

/***/ }),

/***/ "./src/controller/TabLogList.js":
/*!**************************************!*\
  !*** ./src/controller/TabLogList.js ***!
  \**************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _templates_TabCollection_html__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../templates/TabCollection.html */ \"./src/templates/TabCollection.html\");\n/* harmony import */ var _templates_TabCollection_html__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_templates_TabCollection_html__WEBPACK_IMPORTED_MODULE_0__);\n\n\nlet TabLogList = Backbone.View.extend({\n\n    tagName: 'li',\n    events: {\n        \"click .mainentry\": \"mainMenuClick\",\n        \"click .menuEntry\": \"menuEntryClick\"\n    },\n\n    initialize: function(data) {\n        this.listenTo(this.collection, \"sync\", this.render);\n        this.listenTo(this.collection, \"update\", this.render);\n        this.logview = data.logview;\n    },\n\n    render: function() {\n        this.$el.html('');\n        this.renderCollection();\n    },\n\n    renderCollection: function() {\n        this.$el.addClass('dropdown');\n        this.$el.html('');\n        this.$el.append(\n            _templates_TabCollection_html__WEBPACK_IMPORTED_MODULE_0___default()({model: this.collection, name: this.model.attributes.name})\n        );\n    },\n    mainMenuClick: function () {\n        if (this.collection.models[0]) {\n            this.handleElementClick(this.collection.models[0].id);\n        }\n    },\n    menuEntryClick: function (event) {\n        this.handleElementClick(event.target.dataset['modelUuid']);\n    },\n    handleElementClick: function (uuid) {\n        console.log(uuid);\n        console.log(this.logview);\n        this.logview.get_log(this.model.get('logType'), uuid);\n    }\n});\n/* harmony default export */ __webpack_exports__[\"default\"] = (TabLogList);\n\n//# sourceURL=webpack:///./src/controller/TabLogList.js?");

/***/ }),

/***/ "./src/logviewer.js":
/*!**************************!*\
  !*** ./src/logviewer.js ***!
  \**************************/
/*! no exports provided */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _config__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./config */ \"./src/config.js\");\n/* harmony import */ var _controller_LogCategoryList__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./controller/LogCategoryList */ \"./src/controller/LogCategoryList.js\");\n/* harmony import */ var _controller_LogView__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./controller/LogView */ \"./src/controller/LogView.js\");\n\n\n\n\nconst logview = new _controller_LogView__WEBPACK_IMPORTED_MODULE_2__[\"default\"]();\n\nconst menu = new _controller_LogCategoryList__WEBPACK_IMPORTED_MODULE_1__[\"default\"]({\n    collection: _config__WEBPACK_IMPORTED_MODULE_0__[\"defaultEndpoints\"],\n    logview: logview\n});\n\n$(document.getElementById('logapplication'))\n    .append(menu.$el)\n    .append(logview.$el);\nmenu.render();\n\n\n//# sourceURL=webpack:///./src/logviewer.js?");

/***/ }),

/***/ "./src/models/LogCollection.js":
/*!*************************************!*\
  !*** ./src/models/LogCollection.js ***!
  \*************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _LogFileMenuEntry__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./LogFileMenuEntry */ \"./src/models/LogFileMenuEntry.js\");\n\n\nconst LogCollection = Backbone.Collection.extend({\n    model: _LogFileMenuEntry__WEBPACK_IMPORTED_MODULE_0__[\"default\"],\n    url: function () {\n        return '/api/nginx/logs/' + this.logType;\n    },\n    initialize: function (params) {\n        this.logType = params.logType;\n    }\n});\n\n/* harmony default export */ __webpack_exports__[\"default\"] = (LogCollection);\n\n//# sourceURL=webpack:///./src/models/LogCollection.js?");

/***/ }),

/***/ "./src/models/LogFileMenuEntry.js":
/*!****************************************!*\
  !*** ./src/models/LogFileMenuEntry.js ***!
  \****************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony default export */ __webpack_exports__[\"default\"] = (Backbone.Model.extend({\n}));\n\n//# sourceURL=webpack:///./src/models/LogFileMenuEntry.js?");

/***/ }),

/***/ "./src/models/LogLine.js":
/*!*******************************!*\
  !*** ./src/models/LogLine.js ***!
  \*******************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n\nconst LogLine = Backbone.Model.extend({\n});\n\n/* harmony default export */ __webpack_exports__[\"default\"] = (LogLine);\n\n//# sourceURL=webpack:///./src/models/LogLine.js?");

/***/ }),

/***/ "./src/models/LogLinesCollection.js":
/*!******************************************!*\
  !*** ./src/models/LogLinesCollection.js ***!
  \******************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _LogLine__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./LogLine */ \"./src/models/LogLine.js\");\n\n\nconst LogLinesCollection = Backbone.Collection.extend({\n    model: _LogLine__WEBPACK_IMPORTED_MODULE_0__[\"default\"],\n    url: function () {\n        return `/api/nginx/logs/${this.logType}/${this.uuid}`;\n    },\n    initialize: function () {\n        this.logType = 'none';\n        this.uuid = 'none';\n    }\n});\n\n/* harmony default export */ __webpack_exports__[\"default\"] = (LogLinesCollection);\n\n//# sourceURL=webpack:///./src/models/LogLinesCollection.js?");

/***/ }),

/***/ "./src/templates/AccessLogLine.html":
/*!******************************************!*\
  !*** ./src/templates/AccessLogLine.html ***!
  \******************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var _ = {escape:__webpack_require__(/*! ./node_modules/lodash/escape.js */ \"./node_modules/lodash/escape.js\")};\nmodule.exports = function(obj) {\nobj || (obj = {});\nvar __t, __p = '';\nwith (obj) {\n__p += '<tr>\\n<td>' +\n((__t = ( model.escape('time') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('remote_ip') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('username') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('status') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('size') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('user_agent') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('request_line') )) == null ? '' : __t) +\n'</td>\\n</tr>';\n\n}\nreturn __p\n};\n\n//# sourceURL=webpack:///./src/templates/AccessLogLine.html?");

/***/ }),

/***/ "./src/templates/ErrorLogLine.html":
/*!*****************************************!*\
  !*** ./src/templates/ErrorLogLine.html ***!
  \*****************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var _ = {escape:__webpack_require__(/*! ./node_modules/lodash/escape.js */ \"./node_modules/lodash/escape.js\")};\nmodule.exports = function(obj) {\nobj || (obj = {});\nvar __t, __p = '';\nwith (obj) {\n__p += '<tr>\\n<td>' +\n((__t = ( model.escape('date') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('time') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('severity') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('number') )) == null ? '' : __t) +\n'</td>\\n<td>' +\n((__t = ( model.escape('message') )) == null ? '' : __t) +\n'</td>\\n</tr>';\n\n}\nreturn __p\n};\n\n//# sourceURL=webpack:///./src/templates/ErrorLogLine.html?");

/***/ }),

/***/ "./src/templates/TabCollection.html":
/*!******************************************!*\
  !*** ./src/templates/TabCollection.html ***!
  \******************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var _ = {escape:__webpack_require__(/*! ./node_modules/lodash/escape.js */ \"./node_modules/lodash/escape.js\")};\nmodule.exports = function(obj) {\nobj || (obj = {});\nvar __t, __p = '', __j = Array.prototype.join;\nfunction print() { __p += __j.call(arguments, '') }\nwith (obj) {\n\n\n/*\n# Copyright (c) 2017 Franco Fichtner <franco@opnsense.org>\n# Copyright (c) 2017 Fabian Franz\n# Copyright (c) 2014-2015 Deciso B.V.\n# All rights reserved.\n#\n# Redistribution and use in source and binary forms, with or without modification,\n# are permitted provided that the following conditions are met:\n#\n# 1.  Redistributions of source code must retain the above copyright notice,\n#     this list of conditions and the following disclaimer.\n#\n# 2.  Redistributions in binary form must reproduce the above copyright notice,\n#     this list of conditions and the following disclaimer in the documentation\n#     and/or other materials provided with the distribution.\n#\n# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,\n# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY\n# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE\n# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,\n# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF\n# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS\n# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN\n# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)\n# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE\n# POSSIBILITY OF SUCH DAMAGE.\n#\n*/\n;\n__p += '\\n<a data-toggle=\"dropdown\"\\n   href=\"#\"\\n   class=\"dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block\"\\n   role=\"button\">\\n    <b><span class=\"caret\"></span></b>\\n</a>\\n<a data-toggle=\"tab\"\\n   class=\"visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block mainentry\"\\n   style=\"border-right: 0px;\"><b>' +\n((__t = ( _.escape(name) )) == null ? '' : __t) +\n'</b></a>\\n<ul class=\"dropdown-menu\" role=\"menu\">\\n    ';\n model.forEach(function(row) { ;\n__p += '\\n    <li>\\n        <a data-toggle=\"tab\"\\n           class=\"menuEntry\"\\n           data-model-cid=\"' +\n((__t = ( row.cid )) == null ? '' : __t) +\n'\"\\n           data-model-uuid=\"' +\n((__t = ( row.escape('id') )) == null ? '' : __t) +\n'\"\\n           id=\"subtab_item_' +\n((__t = ( row.escape('id') )) == null ? '' : __t) +\n'\"\\n           href=\"#subtab_' +\n((__t = ( row.escape('id') )) == null ? '' : __t) +\n'\">' +\n((__t = ( row.escape('server_name') )) == null ? '' : __t) +\n'</a>\\n    </li>\\n    ';\n }) ;\n__p += '\\n</ul>';\n\n}\nreturn __p\n};\n\n//# sourceURL=webpack:///./src/templates/TabCollection.html?");

/***/ }),

/***/ "./src/templates/logviewer.html":
/*!**************************************!*\
  !*** ./src/templates/logviewer.html ***!
  \**************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

eval("var _ = {escape:__webpack_require__(/*! ./node_modules/lodash/escape.js */ \"./node_modules/lodash/escape.js\")};\nmodule.exports = function(obj) {\nobj || (obj = {});\nvar __t, __p = '', __j = Array.prototype.join;\nfunction print() { __p += __j.call(arguments, '') }\nwith (obj) {\n__p += '<table class=\"table table-striped\">\\n    <thead>\\n        <tr>\\n            ';\n if (log_type === 'errors') { ;\n__p += '\\n            <th>Date</th>\\n            <th>Time</th>\\n            <th>Severity</th>\\n            <th>Number</th>\\n            <th>Message</th>\\n            ';\n } else { ;\n__p += '\\n            <th>Time</th>\\n            <th>Remote IP</th>\\n            <th>Username</th>\\n            <th>Status</th>\\n            <th>Size</th>\\n            <th>User Agent</th>\\n            <th>Request Line</th>\\n            ';\n } ;\n__p += '\\n        </tr>\\n    </thead>\\n    <tbody>' +\n((__t = ( table_body )) == null ? '' : __t) +\n'</tbody>\\n</table>';\n\n}\nreturn __p\n};\n\n//# sourceURL=webpack:///./src/templates/logviewer.html?");

/***/ })

/******/ });