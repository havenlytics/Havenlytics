/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/blocks/agency/block.json":
/*!**************************************!*\
  !*** ./src/blocks/agency/block.json ***!
  \**************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/agency","version":"3.5.0","title":"HVN: Agency","category":"havenlytics","icon":"building","description":"Display a grid or list of agencies with aggregate stats. Reuses the Havenlytics agencies archive template and query.","keywords":["agency","agencies","brokerage","real estate","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true,"blockGap":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"background":true,"text":true,"link":true},"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"defaultView":{"type":"string","default":"grid"},"columns":{"type":"number","default":4},"postsPerPage":{"type":"number","default":12},"orderby":{"type":"string","default":"name"},"order":{"type":"string","default":"ASC"},"showHeader":{"type":"boolean","default":true},"title":{"type":"string","default":""},"subtitle":{"type":"string","default":""},"showSearch":{"type":"boolean","default":true},"showViewControls":{"type":"boolean","default":true}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-frontend-property-agents-archive"}');

/***/ }),

/***/ "./src/blocks/agency/edit.js":
/*!***********************************!*\
  !*** ./src/blocks/agency/edit.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_PeopleArchiveEdit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../shared/PeopleArchiveEdit */ "./src/blocks/shared/PeopleArchiveEdit.js");
/**
 * Agency block — editor component.
 *
 * @package
 * @since   3.5.0
 */



const ORDERBY_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Name', 'havenlytics'),
  value: 'name'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Date added', 'havenlytics'),
  value: 'date'
}];
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ((0,_shared_PeopleArchiveEdit__WEBPACK_IMPORTED_MODULE_1__["default"])('havenlytics/agency', ORDERBY_OPTIONS));

/***/ }),

/***/ "./src/blocks/agency/index.js":
/*!************************************!*\
  !*** ./src/blocks/agency/index.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/agency/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/agency/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * Agency block registration.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/agents/block.json":
/*!**************************************!*\
  !*** ./src/blocks/agents/block.json ***!
  \**************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/agents","version":"3.5.0","title":"HVN: Agents","category":"havenlytics","icon":"groups","description":"Display a grid or list of real estate agents. Reuses the Havenlytics agents archive template and query.","keywords":["agents","team","real estate","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true,"blockGap":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"background":true,"text":true,"link":true},"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"defaultView":{"type":"string","default":"grid"},"columns":{"type":"number","default":4},"postsPerPage":{"type":"number","default":12},"orderby":{"type":"string","default":"title"},"order":{"type":"string","default":"ASC"},"showHeader":{"type":"boolean","default":true},"title":{"type":"string","default":""},"subtitle":{"type":"string","default":""},"showSearch":{"type":"boolean","default":true},"showViewControls":{"type":"boolean","default":true}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-frontend-property-agents-archive"}');

/***/ }),

/***/ "./src/blocks/agents/edit.js":
/*!***********************************!*\
  !*** ./src/blocks/agents/edit.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_PeopleArchiveEdit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../shared/PeopleArchiveEdit */ "./src/blocks/shared/PeopleArchiveEdit.js");
/**
 * Agents block — editor component.
 *
 * @package
 * @since   3.5.0
 */



const ORDERBY_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Name', 'havenlytics'),
  value: 'title'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Date added', 'havenlytics'),
  value: 'date'
}];
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ((0,_shared_PeopleArchiveEdit__WEBPACK_IMPORTED_MODULE_1__["default"])('havenlytics/agents', ORDERBY_OPTIONS));

/***/ }),

/***/ "./src/blocks/agents/index.js":
/*!************************************!*\
  !*** ./src/blocks/agents/index.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/agents/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/agents/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * Agents block registration.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/authentication/block.json":
/*!**********************************************!*\
  !*** ./src/blocks/authentication/block.json ***!
  \**********************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/authentication","version":"3.5.0","title":"HVN: Authentication","category":"havenlytics","icon":"lock","description":"Login, registration and account panel that reuses the Havenlytics Workspace authentication system. Frontend UI only — no new auth logic.","keywords":["login","register","sign in","account","auth","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"background":true,"text":true,"link":true},"shadow":true,"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"authMode":{"type":"string","default":"auto"},"defaultTab":{"type":"string","default":"login"},"layout":{"type":"string","default":"card"},"cardWidth":{"type":"number","default":460},"cardAlign":{"type":"string","default":"center"},"showLogo":{"type":"boolean","default":true},"showHeading":{"type":"boolean","default":true},"showDescription":{"type":"boolean","default":true},"showRemember":{"type":"boolean","default":true},"showForgot":{"type":"boolean","default":true},"showRegisterLink":{"type":"boolean","default":true},"showNames":{"type":"boolean","default":true},"showTerms":{"type":"boolean","default":false},"termsUrl":{"type":"string","default":""},"showWelcomePanel":{"type":"boolean","default":true},"showAvatar":{"type":"boolean","default":true},"showQuickLinks":{"type":"boolean","default":true},"afterLogin":{"type":"string","default":"current"},"afterLoginUrl":{"type":"string","default":""},"afterRegister":{"type":"string","default":"current"},"afterRegisterUrl":{"type":"string","default":""}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-block-auth"}');

/***/ }),

/***/ "./src/blocks/authentication/edit.js":
/*!*******************************************!*\
  !*** ./src/blocks/authentication/edit.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../shared/BlockPreview */ "./src/blocks/shared/BlockPreview.js");

/**
 * HVN: Authentication block — editor component.
 *
 * The preview is the real server render (ServerSideRender via BlockPreview), so
 * the editor is WYSIWYG. All inputs are neutralized inside the editor by the
 * shared EditorPreview guard.
 *
 * @package
 * @since   3.5.0
 */





const MODE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Auto (form when logged out, account when logged in)', 'havenlytics'),
  value: 'auto'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Login only', 'havenlytics'),
  value: 'login'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Register only', 'havenlytics'),
  value: 'register'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Login + Register tabs', 'havenlytics'),
  value: 'tabs'
}];
const LAYOUT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Card', 'havenlytics'),
  value: 'card'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Modern', 'havenlytics'),
  value: 'modern'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Classic', 'havenlytics'),
  value: 'classic'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Minimal', 'havenlytics'),
  value: 'minimal'
}];
const ALIGN_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Left', 'havenlytics'),
  value: 'left'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Center', 'havenlytics'),
  value: 'center'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Right', 'havenlytics'),
  value: 'right'
}];
const REDIRECT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Current page', 'havenlytics'),
  value: 'current'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Dashboard', 'havenlytics'),
  value: 'dashboard'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('My account (profile)', 'havenlytics'),
  value: 'account'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Custom URL', 'havenlytics'),
  value: 'custom'
}];
function Edit({
  attributes,
  setAttributes
}) {
  const {
    authMode,
    defaultTab,
    layout,
    cardWidth,
    cardAlign,
    showLogo,
    showHeading,
    showDescription,
    showRemember,
    showForgot,
    showRegisterLink,
    showNames,
    showTerms,
    termsUrl,
    showWelcomePanel,
    showAvatar,
    showQuickLinks,
    afterLogin,
    afterLoginUrl,
    afterRegister,
    afterRegisterUrl
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  const showsRegister = authMode === 'register' || authMode === 'tabs' || authMode === 'auto';
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('General', 'havenlytics'),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Choose how login and registration appear on this page.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Authentication mode', 'havenlytics'),
    value: authMode,
    options: MODE_OPTIONS,
    onChange: value => setAttributes({
      authMode: value
    }),
    __nextHasNoMarginBottom: true
  }), authMode === 'tabs' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default tab', 'havenlytics'),
    value: defaultTab,
    options: [{
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Login', 'havenlytics'),
      value: 'login'
    }, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Register', 'havenlytics'),
      value: 'register'
    }],
    onChange: value => setAttributes({
      defaultTab: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Layout style', 'havenlytics'),
    value: layout,
    options: LAYOUT_OPTIONS,
    onChange: value => setAttributes({
      layout: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Card width (px)', 'havenlytics'),
    value: cardWidth,
    min: 320,
    max: 720,
    onChange: value => setAttributes({
      cardWidth: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Alignment', 'havenlytics'),
    value: cardAlign,
    options: ALIGN_OPTIONS,
    onChange: value => setAttributes({
      cardAlign: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Display', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show logo', 'havenlytics'),
    checked: !!showLogo,
    onChange: value => setAttributes({
      showLogo: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show heading', 'havenlytics'),
    checked: !!showHeading,
    onChange: value => setAttributes({
      showHeading: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show description', 'havenlytics'),
    checked: !!showDescription,
    onChange: value => setAttributes({
      showDescription: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show "Remember me"', 'havenlytics'),
    checked: !!showRemember,
    onChange: value => setAttributes({
      showRemember: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show "Forgot password"', 'havenlytics'),
    checked: !!showForgot,
    onChange: value => setAttributes({
      showForgot: value
    }),
    __nextHasNoMarginBottom: true
  }), (authMode === 'login' || authMode === 'auto') && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show register link', 'havenlytics'),
    checked: !!showRegisterLink,
    onChange: value => setAttributes({
      showRegisterLink: value
    }),
    __nextHasNoMarginBottom: true
  })), showsRegister && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Registration', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show first / last name fields', 'havenlytics'),
    checked: !!showNames,
    onChange: value => setAttributes({
      showNames: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Require terms checkbox', 'havenlytics'),
    checked: !!showTerms,
    onChange: value => setAttributes({
      showTerms: value
    }),
    __nextHasNoMarginBottom: true
  }), showTerms && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Terms page URL', 'havenlytics'),
    value: termsUrl,
    onChange: value => setAttributes({
      termsUrl: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Logged-in panel', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show welcome panel', 'havenlytics'),
    checked: !!showWelcomePanel,
    onChange: value => setAttributes({
      showWelcomePanel: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show avatar', 'havenlytics'),
    checked: !!showAvatar,
    onChange: value => setAttributes({
      showAvatar: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show quick links', 'havenlytics'),
    checked: !!showQuickLinks,
    onChange: value => setAttributes({
      showQuickLinks: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Redirects', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('After login', 'havenlytics'),
    value: afterLogin,
    options: REDIRECT_OPTIONS,
    onChange: value => setAttributes({
      afterLogin: value
    }),
    __nextHasNoMarginBottom: true
  }), afterLogin === 'custom' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('After login URL', 'havenlytics'),
    value: afterLoginUrl,
    onChange: value => setAttributes({
      afterLoginUrl: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('After registration', 'havenlytics'),
    value: afterRegister,
    options: REDIRECT_OPTIONS,
    onChange: value => setAttributes({
      afterRegister: value
    }),
    __nextHasNoMarginBottom: true
  }), afterRegister === 'custom' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('After registration URL', 'havenlytics'),
    value: afterRegisterUrl,
    onChange: value => setAttributes({
      afterRegisterUrl: value
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
    block: "havenlytics/authentication",
    attributes: attributes
  })));
}

/***/ }),

/***/ "./src/blocks/authentication/index.js":
/*!********************************************!*\
  !*** ./src/blocks/authentication/index.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/authentication/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/authentication/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * HVN: Authentication block registration.
 *
 * Frontend UI only. All authentication runs through the existing Workspace
 * SessionAuthController AJAX actions — this block adds no new auth logic.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/dashboard/block.json":
/*!*****************************************!*\
  !*** ./src/blocks/dashboard/block.json ***!
  \*****************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/dashboard","version":"3.5.0","title":"HVN: Dashboard","category":"havenlytics","icon":"dashboard","description":"Renders the existing Havenlytics Agent Workspace dashboard. Presentation layer only — it reuses the current Workspace app, data, permissions and routing.","keywords":["dashboard","workspace","agent","account","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true},"color":{"background":true,"text":false,"link":false}},"attributes":{"fullScreen":{"type":"boolean","default":true},"dashboardLayout":{"type":"string","default":"default"},"defaultSection":{"type":"string","default":"dashboard"},"containerWidth":{"type":"number","default":0},"minHeight":{"type":"number","default":600},"showHeader":{"type":"boolean","default":true},"showSidebar":{"type":"boolean","default":true},"showWelcome":{"type":"boolean","default":true},"showStatistics":{"type":"boolean","default":true},"showQuickActions":{"type":"boolean","default":true},"showRecentProperties":{"type":"boolean","default":true},"showFavorites":{"type":"boolean","default":true},"showActivity":{"type":"boolean","default":true},"showProfileSummary":{"type":"boolean","default":true},"sidebarCollapse":{"type":"string","default":"auto"},"cardRadius":{"type":"number","default":0},"spacing":{"type":"number","default":0},"shadow":{"type":"string","default":"default"},"containerStyle":{"type":"string","default":"default"},"loggedOutMode":{"type":"string","default":"form"},"loggedOutHeading":{"type":"string","default":""},"loggedOutMessage":{"type":"string","default":""},"authButtonLabel":{"type":"string","default":""},"authUrl":{"type":"string","default":""}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-block-dashboard"}');

/***/ }),

/***/ "./src/blocks/dashboard/edit.js":
/*!**************************************!*\
  !*** ./src/blocks/dashboard/edit.js ***!
  \**************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);

/**
 * HVN: Dashboard block — editor component.
 *
 * The preview is the real server render (ServerSideRender via BlockPreview),
 * which shows an informative panel in the editor (the live Workspace SPA only
 * boots on the frontend for signed-in users).
 *
 * @package
 * @since   3.5.0
 */




const NAV_ITEMS = [{
  id: 'dashboard',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Dashboard', 'havenlytics')
}, {
  id: 'properties',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Properties', 'havenlytics')
}, {
  id: 'saved-properties',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Saved Properties', 'havenlytics')
}, {
  id: 'inquiries',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Inquiries', 'havenlytics')
}, {
  id: 'profile',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Profile', 'havenlytics')
}, {
  id: 'analytics',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Analytics', 'havenlytics')
}, {
  id: 'notifications',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Notifications', 'havenlytics')
}, {
  id: 'settings',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Settings', 'havenlytics')
}];
const STAT_CARDS = [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Active Listings', 'havenlytics'), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('New Inquiries', 'havenlytics'), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Pending Listings', 'havenlytics'), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Closed Volume', 'havenlytics')];

/**
 * A skeleton card shell with a header and body content.
 *
 * @param {Object} props
 * @return {Element}
 */
function SkCard({
  title,
  className,
  children
}) {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: 'hvnly-dbsk__card' + (className ? ' ' + className : '')
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__card-head"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__t hvnly-dbsk__t--title"
  }, title), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__b hvnly-dbsk__b--pill"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__card-body"
  }, children));
}

/**
 * Lightweight, native dashboard skeleton for the editor.
 *
 * Purely presentational (no SPA, no iframe, no data). It mirrors the real
 * Workspace dashboard layout — sidebar, header, statistics, recent properties,
 * recent activity, quick actions — and honors the block's visibility toggles so
 * the editor preview resembles what the frontend will render.
 *
 * @param {Object} props
 * @param {Object} props.attributes Block attributes.
 * @return {Element}
 */
function DashboardSkeleton({
  attributes
}) {
  const {
    showSidebar,
    showHeader,
    showWelcome,
    showStatistics,
    showRecentProperties,
    showActivity,
    showQuickActions,
    defaultSection
  } = attributes;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk",
    "aria-hidden": "true"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__chip"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Preview', 'havenlytics')), showSidebar && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("aside", {
    className: "hvnly-dbsk__sidebar"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__brand"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__brand-dot"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__t hvnly-dbsk__t--brand"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Havenlytics', 'havenlytics'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("nav", {
    className: "hvnly-dbsk__nav"
  }, NAV_ITEMS.map(item => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    key: item.id,
    className: 'hvnly-dbsk__nav-item' + (item.id === defaultSection ? ' is-active' : '')
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__nav-ico"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__nav-label"
  }, item.label))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__main"
  }, showHeader && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("header", {
    className: "hvnly-dbsk__header"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__t hvnly-dbsk__t--pagetitle"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Dashboard', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__header-right"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__search"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__avatar"
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__content"
  }, showWelcome && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__welcome"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__t hvnly-dbsk__t--lg"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__b hvnly-dbsk__b--sub"
  })), showStatistics && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__stats"
  }, STAT_CARDS.map(label => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    key: label,
    className: "hvnly-dbsk__card hvnly-dbsk__stat"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__stat-ico"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__t hvnly-dbsk__t--label"
  }, label), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__b hvnly-dbsk__b--num"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__b hvnly-dbsk__b--tiny"
  })))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__cols"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__col-main"
  }, showActivity && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SkCard, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Recent Activity', 'havenlytics')
  }, [0, 1, 2, 3].map(i => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    key: i,
    className: "hvnly-dbsk__activity-row"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__dot"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__b hvnly-dbsk__b--line"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__b hvnly-dbsk__b--time"
  })))), showRecentProperties && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SkCard, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Recent Properties', 'havenlytics')
  }, [0, 1, 2].map(i => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    key: i,
    className: "hvnly-dbsk__prop-row"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__thumb"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__prop-lines"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__b hvnly-dbsk__b--line"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__b hvnly-dbsk__b--half"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__b hvnly-dbsk__b--price"
  }))))), showQuickActions && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-dbsk__col-side"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SkCard, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Quick Actions', 'havenlytics'),
    className: "hvnly-dbsk__quick"
  }, [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add Property', 'havenlytics'), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('My Properties', 'havenlytics'), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Inquiries', 'havenlytics'), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Edit Profile', 'havenlytics')].map(label => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    key: label,
    className: "hvnly-dbsk__action"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-dbsk__action-ico"
  }), label))))))));
}
const SECTION_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Dashboard', 'havenlytics'),
  value: 'dashboard'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Properties', 'havenlytics'),
  value: 'properties'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Saved Properties', 'havenlytics'),
  value: 'saved-properties'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Inquiries', 'havenlytics'),
  value: 'inquiries'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Profile', 'havenlytics'),
  value: 'profile'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Analytics', 'havenlytics'),
  value: 'analytics'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Notifications', 'havenlytics'),
  value: 'notifications'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Settings', 'havenlytics'),
  value: 'settings'
}];
const LAYOUT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default', 'havenlytics'),
  value: 'default'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Contained', 'havenlytics'),
  value: 'contained'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Full width', 'havenlytics'),
  value: 'full'
}];
const SHADOW_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Inherit', 'havenlytics'),
  value: 'default'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('None', 'havenlytics'),
  value: 'none'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Small', 'havenlytics'),
  value: 'sm'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Medium', 'havenlytics'),
  value: 'md'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Large', 'havenlytics'),
  value: 'lg'
}];
const CONTAINER_STYLE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default', 'havenlytics'),
  value: 'default'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Flat', 'havenlytics'),
  value: 'flat'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Bordered', 'havenlytics'),
  value: 'bordered'
}];
const SIDEBAR_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Auto (app default)', 'havenlytics'),
  value: 'auto'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Expanded', 'havenlytics'),
  value: 'expanded'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Collapsed', 'havenlytics'),
  value: 'collapsed'
}];
function Edit({
  attributes,
  setAttributes
}) {
  const {
    fullScreen,
    dashboardLayout,
    defaultSection,
    containerWidth,
    minHeight,
    showHeader,
    showSidebar,
    showWelcome,
    showStatistics,
    showQuickActions,
    showRecentProperties,
    showFavorites,
    showActivity,
    showProfileSummary,
    sidebarCollapse,
    cardRadius,
    spacing,
    shadow,
    containerStyle,
    loggedOutMode,
    loggedOutHeading,
    loggedOutMessage,
    authButtonLabel,
    authUrl
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('General', 'havenlytics'),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Full screen (match Workspace)', 'havenlytics'),
    checked: !!fullScreen,
    onChange: value => setAttributes({
      fullScreen: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Renders the page through the existing Workspace canvas template — no theme header/footer, full-bleed and pixel-identical to the standalone Agent Workspace. Turn off to embed the dashboard inside the normal themed page.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Dashboard layout', 'havenlytics'),
    value: dashboardLayout,
    options: LAYOUT_OPTIONS,
    onChange: value => setAttributes({
      dashboardLayout: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default section', 'havenlytics'),
    value: defaultSection,
    options: SECTION_OPTIONS,
    onChange: value => setAttributes({
      defaultSection: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('The Workspace view shown first when the dashboard loads.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Container width (px, 0 = inherit)', 'havenlytics'),
    value: containerWidth,
    min: 0,
    max: 1600,
    step: 20,
    onChange: value => setAttributes({
      containerWidth: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Minimum height (px)', 'havenlytics'),
    value: minHeight,
    min: 0,
    max: 1400,
    step: 20,
    onChange: value => setAttributes({
      minHeight: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Display', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show header', 'havenlytics'),
    checked: !!showHeader,
    onChange: value => setAttributes({
      showHeader: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show sidebar', 'havenlytics'),
    checked: !!showSidebar,
    onChange: value => setAttributes({
      showSidebar: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show welcome card', 'havenlytics'),
    checked: !!showWelcome,
    onChange: value => setAttributes({
      showWelcome: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show statistics', 'havenlytics'),
    checked: !!showStatistics,
    onChange: value => setAttributes({
      showStatistics: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show quick actions', 'havenlytics'),
    checked: !!showQuickActions,
    onChange: value => setAttributes({
      showQuickActions: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show recent properties', 'havenlytics'),
    checked: !!showRecentProperties,
    onChange: value => setAttributes({
      showRecentProperties: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show favorites', 'havenlytics'),
    checked: !!showFavorites,
    onChange: value => setAttributes({
      showFavorites: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show activity', 'havenlytics'),
    checked: !!showActivity,
    onChange: value => setAttributes({
      showActivity: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show profile summary', 'havenlytics'),
    checked: !!showProfileSummary,
    onChange: value => setAttributes({
      showProfileSummary: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Responsive', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sidebar', 'havenlytics'),
    value: sidebarCollapse,
    options: SIDEBAR_OPTIONS,
    onChange: value => setAttributes({
      sidebarCollapse: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Initial sidebar state on wide screens. The app stays fully responsive on tablet and mobile.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Style', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Card radius (px, 0 = inherit)', 'havenlytics'),
    value: cardRadius,
    min: 0,
    max: 32,
    onChange: value => setAttributes({
      cardRadius: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Spacing (px, 0 = inherit)', 'havenlytics'),
    value: spacing,
    min: 0,
    max: 48,
    onChange: value => setAttributes({
      spacing: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Shadow', 'havenlytics'),
    value: shadow,
    options: SHADOW_OPTIONS,
    onChange: value => setAttributes({
      shadow: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Container style', 'havenlytics'),
    value: containerStyle,
    options: CONTAINER_STYLE_OPTIONS,
    onChange: value => setAttributes({
      containerStyle: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Signed-out state', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('When signed out, show', 'havenlytics'),
    value: loggedOutMode,
    options: [{
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sign-in form', 'havenlytics'),
      value: 'form'
    }, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sign-in button', 'havenlytics'),
      value: 'button'
    }],
    onChange: value => setAttributes({
      loggedOutMode: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Heading', 'havenlytics'),
    value: loggedOutHeading,
    onChange: value => setAttributes({
      loggedOutHeading: value
    }),
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sign in to your dashboard', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Message', 'havenlytics'),
    value: loggedOutMessage,
    onChange: value => setAttributes({
      loggedOutMessage: value
    }),
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('You must sign in to access your dashboard.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), loggedOutMode === 'button' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Button label', 'havenlytics'),
    value: authButtonLabel,
    onChange: value => setAttributes({
      authButtonLabel: value
    }),
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sign in', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Button URL (blank = Workspace login)', 'havenlytics'),
    value: authUrl,
    onChange: value => setAttributes({
      authUrl: value
    }),
    __nextHasNoMarginBottom: true
  })))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(DashboardSkeleton, {
    attributes: attributes
  })));
}

/***/ }),

/***/ "./src/blocks/dashboard/index.js":
/*!***************************************!*\
  !*** ./src/blocks/dashboard/index.js ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/dashboard/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/dashboard/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * HVN: Dashboard block registration.
 *
 * Presentation layer only. Mounts the EXISTING Agent Workspace SPA (via the
 * existing [hvnly_agent_dashboard] shortcode pipeline) — it adds no dashboard
 * logic, data, permissions or routing of its own.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/editor.scss":
/*!********************************!*\
  !*** ./src/blocks/editor.scss ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/blocks/featured-properties/block.json":
/*!***************************************************!*\
  !*** ./src/blocks/featured-properties/block.json ***!
  \***************************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/featured-properties","version":"3.5.0","title":"HVN: Featured Properties","category":"havenlytics","icon":"star-filled","description":"A premium featured-properties section — grid or carousel layout using your Property Card Builder cards.","keywords":["property","featured","highlighted","real estate","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true,"blockGap":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"background":true,"text":true,"link":true},"shadow":true,"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"layout":{"type":"string","default":"grid"},"columns":{"type":"number","default":3},"postsPerPage":{"type":"number","default":6},"orderby":{"type":"string","default":"date"},"visibleSlides":{"type":"number","default":3},"autoplay":{"type":"boolean","default":false},"showHeader":{"type":"boolean","default":true},"sectionTitle":{"type":"string","default":""},"sectionSubtitle":{"type":"string","default":""},"sectionDescription":{"type":"string","default":""},"sectionAlign":{"type":"string","default":"left"},"sectionButtonShow":{"type":"boolean","default":false},"sectionButtonText":{"type":"string","default":""},"sectionButtonUrl":{"type":"string","default":""},"sectionButtonTarget":{"type":"string","default":"_self"}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-block-premium"}');

/***/ }),

/***/ "./src/blocks/featured-properties/edit.js":
/*!************************************************!*\
  !*** ./src/blocks/featured-properties/edit.js ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../shared/BlockPreview */ "./src/blocks/shared/BlockPreview.js");
/* harmony import */ var _shared_SectionHeaderPanel__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../shared/SectionHeaderPanel */ "./src/blocks/shared/SectionHeaderPanel.js");

/**
 * Featured Properties block — editor component.
 *
 * @package
 * @since   3.5.0
 */






const LAYOUT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Grid', 'havenlytics'),
  value: 'grid'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Carousel', 'havenlytics'),
  value: 'carousel'
}];
const ORDERBY_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Newest first', 'havenlytics'),
  value: 'date'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title (A–Z)', 'havenlytics'),
  value: 'title'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Price: low to high', 'havenlytics'),
  value: 'price_low'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Price: high to low', 'havenlytics'),
  value: 'price_high'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Random', 'havenlytics'),
  value: 'rand'
}];
function Edit({
  attributes,
  setAttributes
}) {
  const {
    layout,
    columns,
    postsPerPage,
    orderby,
    visibleSlides,
    autoplay
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  // Saved "spotlight" blocks fall back to grid (option removed).
  const layoutValue = layout === 'spotlight' ? 'grid' : layout;
  const isCarousel = layoutValue === 'carousel';
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_SectionHeaderPanel__WEBPACK_IMPORTED_MODULE_5__["default"], {
    attributes: attributes,
    setAttributes: setAttributes
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Layout', 'havenlytics'),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Featured listings reuse the Property Card Builder design.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Layout style', 'havenlytics'),
    value: layoutValue,
    options: LAYOUT_OPTIONS,
    onChange: value => setAttributes({
      layout: value
    }),
    __nextHasNoMarginBottom: true
  }), layoutValue === 'grid' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Columns', 'havenlytics'),
    value: columns,
    min: 1,
    max: 4,
    onChange: value => setAttributes({
      columns: value
    }),
    __nextHasNoMarginBottom: true
  }), isCarousel && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Visible slides', 'havenlytics'),
    value: visibleSlides,
    min: 1,
    max: 5,
    onChange: value => setAttributes({
      visibleSlides: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Autoplay', 'havenlytics'),
    checked: !!autoplay,
    onChange: value => setAttributes({
      autoplay: value
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Query', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Number of properties', 'havenlytics'),
    value: postsPerPage,
    min: 1,
    max: 24,
    onChange: value => setAttributes({
      postsPerPage: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Order by', 'havenlytics'),
    value: orderby,
    options: ORDERBY_OPTIONS,
    onChange: value => setAttributes({
      orderby: value
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
    block: "havenlytics/featured-properties",
    attributes: attributes
  })));
}

/***/ }),

/***/ "./src/blocks/featured-properties/index.js":
/*!*************************************************!*\
  !*** ./src/blocks/featured-properties/index.js ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/featured-properties/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/featured-properties/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * Featured Properties block registration.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/property-archive/block.json":
/*!************************************************!*\
  !*** ./src/blocks/property-archive/block.json ***!
  \************************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/property-archive","version":"3.5.0","title":"HVN: Property Archive","category":"havenlytics","icon":"admin-home","description":"Display a filterable grid, list, or map of Havenlytics property listings. Reuses the same query, cards, and templates as the archive page and Elementor widget.","keywords":["property","listings","real estate","archive","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true,"blockGap":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"background":true,"text":true,"link":true},"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"defaultView":{"type":"string","default":"grid"},"columns":{"type":"number","default":2},"postsPerPage":{"type":"number","default":12},"orderby":{"type":"string","default":"date"},"featuredOnly":{"type":"boolean","default":false},"showTopSearch":{"type":"boolean","default":false},"showFilterSidebar":{"type":"boolean","default":true},"sidebarPosition":{"type":"string","default":"left"},"department":{"type":"string","default":""},"propertyType":{"type":"array","default":[],"items":{"type":"string"}},"status":{"type":"array","default":[],"items":{"type":"string"}},"location":{"type":"array","default":[],"items":{"type":"string"}}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-frontend-property-archive"}');

/***/ }),

/***/ "./src/blocks/property-archive/edit.js":
/*!*********************************************!*\
  !*** ./src/blocks/property-archive/edit.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../shared/BlockPreview */ "./src/blocks/shared/BlockPreview.js");

/**
 * Property Archive block — editor component.
 *
 * Provides a premium inspector control surface (matching the Elementor
 * "HVN: Property Archive" widget plus native block supports) and a live
 * server-rendered preview via ServerSideRender, so the editor experience mirrors
 * the real frontend output.
 *
 * @package
 * @since   3.5.0
 */





const VIEW_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Grid', 'havenlytics'),
  value: 'grid'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('List', 'havenlytics'),
  value: 'list'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Map', 'havenlytics'),
  value: 'map'
}];
const ORDERBY_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Newest first', 'havenlytics'),
  value: 'date'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title (A–Z)', 'havenlytics'),
  value: 'title'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Price: low to high', 'havenlytics'),
  value: 'price_low'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Price: high to low', 'havenlytics'),
  value: 'price_high'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Random', 'havenlytics'),
  value: 'rand'
}];
const SIDEBAR_POSITION_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Left', 'havenlytics'),
  value: 'left'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Right', 'havenlytics'),
  value: 'right'
}];

/**
 * Normalize a FormTokenField value (array of strings) into slugs.
 *
 * @param {Array} tokens Raw tokens from FormTokenField.
 * @return {Array} Trimmed, non-empty tokens.
 */
function cleanTokens(tokens) {
  if (!Array.isArray(tokens)) {
    return [];
  }
  return tokens.map(token => String(token).trim()).filter(token => token.length > 0);
}
function Edit({
  attributes,
  setAttributes
}) {
  const {
    defaultView,
    columns,
    postsPerPage,
    orderby,
    featuredOnly,
    showTopSearch,
    showFilterSidebar,
    sidebarPosition,
    department,
    propertyType,
    status,
    location
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Layout', 'havenlytics'),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Same archive experience as the Elementor Property Archive widget.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default view', 'havenlytics'),
    value: defaultView,
    options: VIEW_OPTIONS,
    onChange: value => setAttributes({
      defaultView: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Columns', 'havenlytics'),
    value: columns,
    min: 1,
    max: 4,
    onChange: value => setAttributes({
      columns: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Applies to grid view.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show top search bar', 'havenlytics'),
    checked: !!showTopSearch,
    onChange: value => setAttributes({
      showTopSearch: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show filter sidebar', 'havenlytics'),
    checked: !!showFilterSidebar,
    onChange: value => setAttributes({
      showFilterSidebar: value
    }),
    __nextHasNoMarginBottom: true
  }), showFilterSidebar && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sidebar position', 'havenlytics'),
    value: sidebarPosition,
    options: SIDEBAR_POSITION_OPTIONS,
    onChange: value => setAttributes({
      sidebarPosition: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Query', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Properties per page', 'havenlytics'),
    value: postsPerPage,
    min: 1,
    max: 48,
    onChange: value => setAttributes({
      postsPerPage: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Order by', 'havenlytics'),
    value: orderby,
    options: ORDERBY_OPTIONS,
    onChange: value => setAttributes({
      orderby: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Featured properties only', 'havenlytics'),
    checked: !!featuredOnly,
    onChange: value => setAttributes({
      featuredOnly: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Taxonomy filters', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
    status: "info",
    isDismissible: false
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Enter term slugs to pre-filter the listing. Visitors can still refine results with the search filters.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Department (slug)', 'havenlytics'),
    value: department,
    onChange: value => setAttributes({
      department: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FormTokenField, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Property types (slugs)', 'havenlytics'),
    value: propertyType,
    onChange: tokens => setAttributes({
      propertyType: cleanTokens(tokens)
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FormTokenField, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Status (slugs)', 'havenlytics'),
    value: status,
    onChange: tokens => setAttributes({
      status: cleanTokens(tokens)
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FormTokenField, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Locations (slugs)', 'havenlytics'),
    value: location,
    onChange: tokens => setAttributes({
      location: cleanTokens(tokens)
    })
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
    block: "havenlytics/property-archive",
    attributes: attributes
  })));
}

/***/ }),

/***/ "./src/blocks/property-archive/index.js":
/*!**********************************************!*\
  !*** ./src/blocks/property-archive/index.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/property-archive/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/property-archive/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * Property Archive block registration.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/property-carousel/block.json":
/*!*************************************************!*\
  !*** ./src/blocks/property-carousel/block.json ***!
  \*************************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/property-carousel","version":"3.5.0","title":"HVN: Property Carousel","category":"havenlytics","icon":"images-alt2","description":"A premium property carousel (center mode, autoplay, arrows, dots, touch, responsive) using your Property Card Builder cards.","keywords":["property","carousel","slider","real estate","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true,"blockGap":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"background":true,"text":true,"link":true},"shadow":true,"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"postsPerPage":{"type":"number","default":9},"visibleSlides":{"type":"number","default":3},"visibleTablet":{"type":"number","default":2},"visibleMobile":{"type":"number","default":1},"orderby":{"type":"string","default":"date"},"featuredOnly":{"type":"boolean","default":false},"centerMode":{"type":"boolean","default":false},"autoplay":{"type":"boolean","default":false},"showNav":{"type":"boolean","default":true},"showDots":{"type":"boolean","default":true},"showHeader":{"type":"boolean","default":false},"sectionTitle":{"type":"string","default":""},"sectionSubtitle":{"type":"string","default":""},"sectionDescription":{"type":"string","default":""},"sectionAlign":{"type":"string","default":"left"},"sectionButtonShow":{"type":"boolean","default":false},"sectionButtonText":{"type":"string","default":""},"sectionButtonUrl":{"type":"string","default":""},"sectionButtonTarget":{"type":"string","default":"_self"}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-block-premium"}');

/***/ }),

/***/ "./src/blocks/property-carousel/edit.js":
/*!**********************************************!*\
  !*** ./src/blocks/property-carousel/edit.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../shared/BlockPreview */ "./src/blocks/shared/BlockPreview.js");
/* harmony import */ var _shared_SectionHeaderPanel__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../shared/SectionHeaderPanel */ "./src/blocks/shared/SectionHeaderPanel.js");

/**
 * Property Carousel block — editor component.
 *
 * @package
 * @since   3.5.0
 */






const ORDERBY_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Newest first', 'havenlytics'),
  value: 'date'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title (A–Z)', 'havenlytics'),
  value: 'title'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Price: low to high', 'havenlytics'),
  value: 'price_low'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Price: high to low', 'havenlytics'),
  value: 'price_high'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Random', 'havenlytics'),
  value: 'rand'
}];
function Edit({
  attributes,
  setAttributes
}) {
  const {
    postsPerPage,
    visibleSlides,
    visibleTablet,
    visibleMobile,
    orderby,
    featuredOnly,
    centerMode,
    autoplay,
    showNav,
    showDots
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_SectionHeaderPanel__WEBPACK_IMPORTED_MODULE_5__["default"], {
    attributes: attributes,
    setAttributes: setAttributes
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Carousel', 'havenlytics'),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Slide count and navigation for the property carousel.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Center mode', 'havenlytics'),
    checked: !!centerMode,
    onChange: value => setAttributes({
      centerMode: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Autoplay', 'havenlytics'),
    checked: !!autoplay,
    onChange: value => setAttributes({
      autoplay: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show arrows', 'havenlytics'),
    checked: !!showNav,
    onChange: value => setAttributes({
      showNav: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show dots', 'havenlytics'),
    checked: !!showDots,
    onChange: value => setAttributes({
      showDots: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Responsive slides', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Desktop', 'havenlytics'),
    value: visibleSlides,
    min: 1,
    max: 5,
    onChange: value => setAttributes({
      visibleSlides: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Tablet', 'havenlytics'),
    value: visibleTablet,
    min: 1,
    max: 4,
    onChange: value => setAttributes({
      visibleTablet: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Mobile', 'havenlytics'),
    value: visibleMobile,
    min: 1,
    max: 2,
    onChange: value => setAttributes({
      visibleMobile: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Query', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Number of properties', 'havenlytics'),
    value: postsPerPage,
    min: 1,
    max: 24,
    onChange: value => setAttributes({
      postsPerPage: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Order by', 'havenlytics'),
    value: orderby,
    options: ORDERBY_OPTIONS,
    onChange: value => setAttributes({
      orderby: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Featured only', 'havenlytics'),
    checked: !!featuredOnly,
    onChange: value => setAttributes({
      featuredOnly: value
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
    block: "havenlytics/property-carousel",
    attributes: attributes
  })));
}

/***/ }),

/***/ "./src/blocks/property-carousel/index.js":
/*!***********************************************!*\
  !*** ./src/blocks/property-carousel/index.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/property-carousel/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/property-carousel/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * Property Carousel block registration.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/property-inquiry/block.json":
/*!************************************************!*\
  !*** ./src/blocks/property-inquiry/block.json ***!
  \************************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/property-inquiry","version":"3.5.0","title":"HVN: Property Inquiry Form","category":"havenlytics","icon":"email-alt","description":"Drop the existing Havenlytics inquiry form on any page. A frontend entry point over the Contact Agent system — reuses its storage, validation, spam protection, emails, agent assignment and success/error UI. No new inquiry logic.","keywords":["inquiry","enquiry","contact","contact agent","lead","message","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"background":true,"text":true},"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"propertySource":{"type":"string","default":"current"},"propertyId":{"type":"number","default":0},"propertyIds":{"type":"array","default":[]},"fallbackPropertyId":{"type":"number","default":0},"agentSource":{"type":"string","default":"auto"},"agentId":{"type":"number","default":0},"agentIds":{"type":"array","default":[]},"showTitle":{"type":"boolean","default":true},"formTitle":{"type":"string","default":"Send an Inquiry"},"showDescription":{"type":"boolean","default":false},"formDescription":{"type":"string","default":""},"showPropertyImage":{"type":"boolean","default":false},"showPropertyPrice":{"type":"boolean","default":false},"showPropertyAddress":{"type":"boolean","default":false},"showAgentCard":{"type":"boolean","default":false},"layout":{"type":"string","default":"vertical"},"formWidth":{"type":"number","default":0},"showName":{"type":"boolean","default":true},"showEmail":{"type":"boolean","default":true},"showPhone":{"type":"boolean","default":true},"showMessage":{"type":"boolean","default":true},"showConsent":{"type":"boolean","default":false},"consentText":{"type":"string","default":"I agree to be contacted about my inquiry."},"buttonText":{"type":"string","default":""},"buttonWidth":{"type":"string","default":"auto"},"buttonAlign":{"type":"string","default":"left"},"buttonIcon":{"type":"string","default":"none"},"showLoadingState":{"type":"boolean","default":true},"showSuccessState":{"type":"boolean","default":true},"successMessage":{"type":"string","default":""},"successRedirectUrl":{"type":"string","default":""}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-block-inquiry"}');

/***/ }),

/***/ "./src/blocks/property-inquiry/edit.js":
/*!*********************************************!*\
  !*** ./src/blocks/property-inquiry/edit.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../shared/BlockPreview */ "./src/blocks/shared/BlockPreview.js");
/* harmony import */ var _shared_EntitySearchControl__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../shared/EntitySearchControl */ "./src/blocks/shared/EntitySearchControl.js");

/**
 * HVN: Property Inquiry Form block — editor component.
 *
 * Inspector UX only. The live preview is ServerSideRender of the reused Contact
 * Agent form. No inquiry business logic lives here.
 *
 * Normal users pick properties/agents by search — never by ID.
 * Manual IDs live under Advanced for developers only.
 *
 * @package
 * @since   3.5.0
 */






const PROPERTY_SOURCE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Current Property', 'havenlytics'),
  value: 'current'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Selected Property', 'havenlytics'),
  value: 'selected'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Multiple Properties', 'havenlytics'),
  value: 'multiple'
}];
const AGENT_SOURCE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Property assigned agent(s)', 'havenlytics'),
  value: 'auto'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Current agent (agent pages)', 'havenlytics'),
  value: 'current'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Selected Agent', 'havenlytics'),
  value: 'selected'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Multiple Agents', 'havenlytics'),
  value: 'multiple'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No specific agent', 'havenlytics'),
  value: 'none'
}];
const LAYOUT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Vertical', 'havenlytics'),
  value: 'vertical'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Horizontal', 'havenlytics'),
  value: 'horizontal'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('2 Column', 'havenlytics'),
  value: 'two-column'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Compact', 'havenlytics'),
  value: 'compact'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Card', 'havenlytics'),
  value: 'card'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Minimal', 'havenlytics'),
  value: 'minimal'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Floating Card', 'havenlytics'),
  value: 'floating'
}];
const BUTTON_WIDTH_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Auto', 'havenlytics'),
  value: 'auto'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Full width', 'havenlytics'),
  value: 'full'
}];
const BUTTON_ALIGN_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Left', 'havenlytics'),
  value: 'left'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Center', 'havenlytics'),
  value: 'center'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Right', 'havenlytics'),
  value: 'right'
}];
const BUTTON_ICON_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('None', 'havenlytics'),
  value: 'none'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Send', 'havenlytics'),
  value: 'send'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Envelope', 'havenlytics'),
  value: 'envelope'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Check', 'havenlytics'),
  value: 'check'
}];
function Edit({
  attributes,
  setAttributes
}) {
  const {
    propertySource,
    propertyId,
    propertyIds,
    fallbackPropertyId,
    agentSource,
    agentId,
    agentIds,
    showTitle,
    formTitle,
    showDescription,
    formDescription,
    showPropertyImage,
    showPropertyPrice,
    showPropertyAddress,
    showAgentCard,
    layout,
    formWidth,
    showName,
    showEmail,
    showPhone,
    showMessage,
    showConsent,
    consentText,
    buttonText,
    buttonWidth,
    buttonAlign,
    buttonIcon,
    showLoadingState,
    showSuccessState,
    successMessage,
    successRedirectUrl
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  const ids = Array.isArray(propertyIds) ? propertyIds : [];
  const aIds = Array.isArray(agentIds) ? agentIds : [];
  const propertySourceOptions = propertySource === 'manual' ? [...PROPERTY_SOURCE_OPTIONS, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Manual Property ID (Advanced)', 'havenlytics'),
    value: 'manual'
  }] : PROPERTY_SOURCE_OPTIONS;
  const agentSourceOptions = agentSource === 'manual' ? [...AGENT_SOURCE_OPTIONS, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Manual Agent ID (Advanced)', 'havenlytics'),
    value: 'manual'
  }] : AGENT_SOURCE_OPTIONS;
  const missingProperty = propertySource === 'selected' && !propertyId || propertySource === 'manual' && !propertyId || propertySource === 'multiple' && ids.length === 0 || propertySource === 'current' && !fallbackPropertyId;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Content', 'havenlytics'),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Choose which property and agent this form is about. Visitors never see IDs.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Property source', 'havenlytics'),
    value: propertySource,
    options: propertySourceOptions,
    onChange: value => setAttributes({
      propertySource: value
    }),
    __nextHasNoMarginBottom: true
  }), propertySource === 'selected' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_EntitySearchControl__WEBPACK_IMPORTED_MODULE_5__["default"], {
    postType: "hvnly_property",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Property', 'havenlytics'),
    value: propertyId,
    onChange: value => setAttributes({
      propertyId: value || 0
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Browse published properties. Search is optional.', 'havenlytics')
  }), propertySource === 'multiple' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_EntitySearchControl__WEBPACK_IMPORTED_MODULE_5__["default"], {
    postType: "hvnly_property",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Properties', 'havenlytics'),
    multiple: true,
    value: ids,
    onChange: value => setAttributes({
      propertyIds: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Visitors pick one title; the existing property_id is submitted.', 'havenlytics')
  }), propertySource === 'current' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_EntitySearchControl__WEBPACK_IMPORTED_MODULE_5__["default"], {
    postType: "hvnly_property",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Fallback property', 'havenlytics'),
    value: fallbackPropertyId,
    onChange: value => setAttributes({
      fallbackPropertyId: value || 0
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Used when this page is not a single property.', 'havenlytics')
  }), propertySource === 'manual' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
    status: "info",
    isDismissible: false
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Manual Property ID is enabled under Advanced.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Agent source', 'havenlytics'),
    value: agentSource,
    options: agentSourceOptions,
    onChange: value => setAttributes({
      agentSource: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Recipients are still validated by the Contact Agent system.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), agentSource === 'selected' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_EntitySearchControl__WEBPACK_IMPORTED_MODULE_5__["default"], {
    postType: "hvnly_agent",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Agent', 'havenlytics'),
    value: agentId,
    onChange: value => setAttributes({
      agentId: value || 0
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Browse published agents. Search is optional.', 'havenlytics')
  }), agentSource === 'multiple' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_EntitySearchControl__WEBPACK_IMPORTED_MODULE_5__["default"], {
    postType: "hvnly_agent",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Agents', 'havenlytics'),
    multiple: true,
    value: aIds,
    onChange: value => setAttributes({
      agentIds: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Visitors choose who receives the inquiry.', 'havenlytics')
  }), agentSource === 'manual' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
    status: "info",
    isDismissible: false
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Manual Agent ID is enabled under Advanced.', 'havenlytics'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Header & preview', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show title', 'havenlytics'),
    checked: !!showTitle,
    onChange: value => setAttributes({
      showTitle: value
    }),
    __nextHasNoMarginBottom: true
  }), showTitle && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title', 'havenlytics'),
    value: formTitle,
    onChange: value => setAttributes({
      formTitle: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show description', 'havenlytics'),
    checked: !!showDescription,
    onChange: value => setAttributes({
      showDescription: value
    }),
    __nextHasNoMarginBottom: true
  }), showDescription && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Description', 'havenlytics'),
    value: formDescription,
    onChange: value => setAttributes({
      formDescription: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show property image', 'havenlytics'),
    checked: !!showPropertyImage,
    onChange: value => setAttributes({
      showPropertyImage: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show property price', 'havenlytics'),
    checked: !!showPropertyPrice,
    onChange: value => setAttributes({
      showPropertyPrice: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show property address', 'havenlytics'),
    checked: !!showPropertyAddress,
    onChange: value => setAttributes({
      showPropertyAddress: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show agent card', 'havenlytics'),
    checked: !!showAgentCard,
    onChange: value => setAttributes({
      showAgentCard: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Compact agent summary above the form.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Layout', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Form layout', 'havenlytics'),
    value: layout,
    options: LAYOUT_OPTIONS,
    onChange: value => setAttributes({
      layout: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Card and Floating wrap the form in a bordered surface.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Max width (px)', 'havenlytics'),
    value: formWidth,
    min: 0,
    max: 900,
    onChange: value => setAttributes({
      formWidth: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('0 keeps the form full width of its column.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Fields', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Name, email and message stay required by the inquiry system.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show name', 'havenlytics'),
    checked: showName !== false,
    onChange: value => setAttributes({
      showName: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show email', 'havenlytics'),
    checked: showEmail !== false,
    onChange: value => setAttributes({
      showEmail: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show phone', 'havenlytics'),
    checked: showPhone !== false,
    onChange: value => setAttributes({
      showPhone: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show message', 'havenlytics'),
    checked: showMessage !== false,
    onChange: value => setAttributes({
      showMessage: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show consent checkbox', 'havenlytics'),
    checked: !!showConsent,
    onChange: value => setAttributes({
      showConsent: value
    }),
    __nextHasNoMarginBottom: true
  }), showConsent && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Consent text', 'havenlytics'),
    value: consentText,
    onChange: value => setAttributes({
      consentText: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Submit & success', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Button text', 'havenlytics'),
    value: buttonText,
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Leave empty for default', 'havenlytics'),
    onChange: value => setAttributes({
      buttonText: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Button width', 'havenlytics'),
    value: buttonWidth,
    options: BUTTON_WIDTH_OPTIONS,
    onChange: value => setAttributes({
      buttonWidth: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Button alignment', 'havenlytics'),
    value: buttonAlign,
    options: BUTTON_ALIGN_OPTIONS,
    onChange: value => setAttributes({
      buttonAlign: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Button icon', 'havenlytics'),
    value: buttonIcon,
    options: BUTTON_ICON_OPTIONS,
    onChange: value => setAttributes({
      buttonIcon: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show loading state', 'havenlytics'),
    checked: showLoadingState !== false,
    onChange: value => setAttributes({
      showLoadingState: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show success notice', 'havenlytics'),
    checked: showSuccessState !== false,
    onChange: value => setAttributes({
      showSuccessState: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Custom success message', 'havenlytics'),
    value: successMessage,
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Optional overlay on the existing success notice.', 'havenlytics'),
    onChange: value => setAttributes({
      successMessage: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    type: "url",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Redirect after success', 'havenlytics'),
    value: successRedirectUrl,
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Optional URL after the success notice appears.', 'havenlytics'),
    onChange: value => setAttributes({
      successRedirectUrl: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Advanced', 'havenlytics'),
    initialOpen: propertySource === 'manual' || agentSource === 'manual'
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Developer fallbacks. Prefer the searchable pickers above.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Manual Property ID', 'havenlytics'),
    checked: propertySource === 'manual',
    onChange: enabled => setAttributes({
      propertySource: enabled ? 'manual' : 'selected'
    }),
    __nextHasNoMarginBottom: true
  }), propertySource === 'manual' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    type: "number",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Property ID', 'havenlytics'),
    value: propertyId || '',
    onChange: value => setAttributes({
      propertyId: value ? parseInt(value, 10) : 0
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Manual Agent ID', 'havenlytics'),
    checked: agentSource === 'manual',
    onChange: enabled => setAttributes({
      agentSource: enabled ? 'manual' : 'auto'
    }),
    __nextHasNoMarginBottom: true
  }), agentSource === 'manual' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    type: "number",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Agent ID', 'havenlytics'),
    value: agentId || '',
    onChange: value => setAttributes({
      agentId: value ? parseInt(value, 10) : 0
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, missingProperty && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
    status: "warning",
    isDismissible: false
  }, propertySource === 'multiple' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Choose at least one property so visitors can select where to send their inquiry.', 'havenlytics') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Choose a property for this inquiry form. The Havenlytics inquiry system requires a published property.', 'havenlytics')), propertySource === 'multiple' && ids.length > 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
    status: "info",
    isDismissible: false
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Multiple properties selected — the form will show a property dropdown with titles.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
    block: "havenlytics/property-inquiry",
    attributes: attributes
  })));
}

/***/ }),

/***/ "./src/blocks/property-inquiry/index.js":
/*!**********************************************!*\
  !*** ./src/blocks/property-inquiry/index.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/property-inquiry/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/property-inquiry/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * HVN: Property Inquiry Form block registration.
 *
 * Frontend entry point only. The form markup, submission, validation, spam
 * protection, emails, lead storage and agent assignment all come from the
 * existing Contact Agent system — this block adds no inquiry logic.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/property-map/block.json":
/*!********************************************!*\
  !*** ./src/blocks/property-map/block.json ***!
  \********************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/property-map","version":"3.5.0","title":"HVN: Property Map","category":"havenlytics","icon":"location-alt","description":"A premium interactive property map with marker clustering, popup cards, and map controls.","keywords":["property","map","location","markers","real estate","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true},"color":{"background":true,"text":true},"shadow":true,"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"height":{"type":"number","default":520},"zoom":{"type":"number","default":12},"clustering":{"type":"boolean","default":true},"clusterRadius":{"type":"number","default":48},"clusterMaxZoom":{"type":"number","default":0},"mapStyle":{"type":"string","default":"standard"},"currentLocation":{"type":"boolean","default":true},"autoFit":{"type":"boolean","default":true},"scrollWheel":{"type":"boolean","default":false},"postsPerPage":{"type":"number","default":48},"showAll":{"type":"boolean","default":false},"featuredOnly":{"type":"boolean","default":false},"department":{"type":"string","default":""},"status":{"type":"string","default":""},"propertyType":{"type":"string","default":""},"markerSize":{"type":"string","default":"md"},"markerStyle":{"type":"string","default":"pin"},"popupStyle":{"type":"string","default":"default"},"popupWidth":{"type":"number","default":300},"popupTrigger":{"type":"string","default":"click"},"animations":{"type":"boolean","default":true},"showPrice":{"type":"boolean","default":true},"showFavorite":{"type":"boolean","default":true},"showStatus":{"type":"boolean","default":true},"showMeta":{"type":"boolean","default":true},"showCta":{"type":"boolean","default":true}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-block-premium"}');

/***/ }),

/***/ "./src/blocks/property-map/edit.js":
/*!*****************************************!*\
  !*** ./src/blocks/property-map/edit.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../shared/BlockPreview */ "./src/blocks/shared/BlockPreview.js");

/**
 * Property Map block — editor component.
 *
 * @package
 * @since   3.5.0
 */





const STYLE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Standard', 'havenlytics'),
  value: 'standard'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Light', 'havenlytics'),
  value: 'light'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Dark', 'havenlytics'),
  value: 'dark'
}];
const MARKER_SIZE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Small', 'havenlytics'),
  value: 'sm'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Medium', 'havenlytics'),
  value: 'md'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Large', 'havenlytics'),
  value: 'lg'
}];
const MARKER_STYLE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Pin', 'havenlytics'),
  value: 'pin'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Dot', 'havenlytics'),
  value: 'dot'
}];
const POPUP_STYLE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default card', 'havenlytics'),
  value: 'default'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Compact card', 'havenlytics'),
  value: 'compact'
}];
const POPUP_TRIGGER_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Open on click', 'havenlytics'),
  value: 'click'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Open on hover', 'havenlytics'),
  value: 'hover'
}];
function Edit({
  attributes,
  setAttributes
}) {
  const {
    height,
    zoom,
    clustering,
    clusterRadius,
    clusterMaxZoom,
    mapStyle,
    currentLocation,
    autoFit,
    scrollWheel,
    postsPerPage,
    showAll,
    featuredOnly,
    department,
    status,
    propertyType,
    markerSize,
    markerStyle,
    popupStyle,
    popupWidth,
    popupTrigger,
    animations,
    showPrice,
    showFavorite,
    showStatus,
    showMeta,
    showCta
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Map', 'havenlytics'),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Uses your global Havenlytics map provider and API settings.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Height (px)', 'havenlytics'),
    value: height,
    min: 240,
    max: 1000,
    step: 20,
    onChange: value => setAttributes({
      height: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default zoom', 'havenlytics'),
    value: zoom,
    min: 1,
    max: 20,
    onChange: value => setAttributes({
      zoom: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Map style', 'havenlytics'),
    value: mapStyle,
    options: STYLE_OPTIONS,
    onChange: value => setAttributes({
      mapStyle: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Auto-fit to markers', 'havenlytics'),
    checked: !!autoFit,
    onChange: value => setAttributes({
      autoFit: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Marker clustering', 'havenlytics'),
    checked: !!clustering,
    onChange: value => setAttributes({
      clustering: value
    }),
    __nextHasNoMarginBottom: true
  }), !!clustering && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Cluster radius', 'havenlytics'),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('How close markers must be to group.', 'havenlytics'),
    value: clusterRadius,
    min: 20,
    max: 180,
    step: 4,
    onChange: value => setAttributes({
      clusterRadius: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Disable clustering at zoom', 'havenlytics'),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('0 keeps clustering at every zoom level.', 'havenlytics'),
    value: clusterMaxZoom,
    min: 0,
    max: 19,
    onChange: value => setAttributes({
      clusterMaxZoom: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Current location button', 'havenlytics'),
    checked: !!currentLocation,
    onChange: value => setAttributes({
      currentLocation: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Scroll-wheel zoom', 'havenlytics'),
    checked: !!scrollWheel,
    onChange: value => setAttributes({
      scrollWheel: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Markers', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Marker size', 'havenlytics'),
    value: markerSize,
    options: MARKER_SIZE_OPTIONS,
    onChange: value => setAttributes({
      markerSize: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Marker style', 'havenlytics'),
    value: markerStyle,
    options: MARKER_STYLE_OPTIONS,
    onChange: value => setAttributes({
      markerStyle: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Popup card', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Popup style', 'havenlytics'),
    value: popupStyle,
    options: POPUP_STYLE_OPTIONS,
    onChange: value => setAttributes({
      popupStyle: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Popup width (px)', 'havenlytics'),
    value: popupWidth,
    min: 240,
    max: 360,
    step: 10,
    onChange: value => setAttributes({
      popupWidth: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Popup trigger', 'havenlytics'),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Touch devices always open on tap.', 'havenlytics'),
    value: popupTrigger,
    options: POPUP_TRIGGER_OPTIONS,
    onChange: value => setAttributes({
      popupTrigger: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Animations', 'havenlytics'),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Marker pulse and popup entrance. Reduced-motion preferences are always honored.', 'havenlytics'),
    checked: !!animations,
    onChange: value => setAttributes({
      animations: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Display', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show price', 'havenlytics'),
    checked: !!showPrice,
    onChange: value => setAttributes({
      showPrice: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show favorite button', 'havenlytics'),
    checked: !!showFavorite,
    onChange: value => setAttributes({
      showFavorite: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show status badge', 'havenlytics'),
    checked: !!showStatus,
    onChange: value => setAttributes({
      showStatus: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show meta (beds/baths/area)', 'havenlytics'),
    checked: !!showMeta,
    onChange: value => setAttributes({
      showMeta: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show CTA button', 'havenlytics'),
    checked: !!showCta,
    onChange: value => setAttributes({
      showCta: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Properties', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show all properties', 'havenlytics'),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Loads every mappable property (capped at 500 for performance).', 'havenlytics'),
    checked: !!showAll,
    onChange: value => setAttributes({
      showAll: value
    }),
    __nextHasNoMarginBottom: true
  }), !showAll && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Max properties', 'havenlytics'),
    value: postsPerPage,
    min: 1,
    max: 200,
    step: 1,
    onChange: value => setAttributes({
      postsPerPage: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Featured only', 'havenlytics'),
    checked: !!featuredOnly,
    onChange: value => setAttributes({
      featuredOnly: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Department (slug)', 'havenlytics'),
    value: department,
    onChange: value => setAttributes({
      department: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Status (slug)', 'havenlytics'),
    value: status,
    onChange: value => setAttributes({
      status: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Property type (slug)', 'havenlytics'),
    value: propertyType,
    onChange: value => setAttributes({
      propertyType: value
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
    block: "havenlytics/property-map",
    attributes: attributes
  })));
}

/***/ }),

/***/ "./src/blocks/property-map/index.js":
/*!******************************************!*\
  !*** ./src/blocks/property-map/index.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/property-map/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/property-map/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * Property Map block registration.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/property-search/block.json":
/*!***********************************************!*\
  !*** ./src/blocks/property-search/block.json ***!
  \***********************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/property-search","version":"3.5.0","title":"HVN: Property Search","category":"havenlytics","icon":"search","description":"A search-first property listing: prominent search bar and filter sidebar over a live results grid. Reuses the Havenlytics archive query and templates.","keywords":["property","search","filter","real estate","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true,"blockGap":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"background":true,"text":true,"link":true},"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"defaultView":{"type":"string","default":"grid"},"columns":{"type":"number","default":3},"postsPerPage":{"type":"number","default":12},"orderby":{"type":"string","default":"date"},"featuredOnly":{"type":"boolean","default":false},"showFilterSidebar":{"type":"boolean","default":true},"sidebarPosition":{"type":"string","default":"left"},"department":{"type":"string","default":""},"propertyType":{"type":"array","default":[],"items":{"type":"string"}},"status":{"type":"array","default":[],"items":{"type":"string"}},"location":{"type":"array","default":[],"items":{"type":"string"}}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-frontend-property-archive"}');

/***/ }),

/***/ "./src/blocks/property-search/edit.js":
/*!********************************************!*\
  !*** ./src/blocks/property-search/edit.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../shared/BlockPreview */ "./src/blocks/shared/BlockPreview.js");

/**
 * Property Search block — editor component.
 *
 * Search-first variant of the property archive. Shares the same server render
 * pipeline; the search bar is always shown on the frontend.
 *
 * @package
 * @since   3.5.0
 */





const VIEW_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Grid', 'havenlytics'),
  value: 'grid'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('List', 'havenlytics'),
  value: 'list'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Map', 'havenlytics'),
  value: 'map'
}];
const ORDERBY_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Newest first', 'havenlytics'),
  value: 'date'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title (A–Z)', 'havenlytics'),
  value: 'title'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Price: low to high', 'havenlytics'),
  value: 'price_low'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Price: high to low', 'havenlytics'),
  value: 'price_high'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Random', 'havenlytics'),
  value: 'rand'
}];
const SIDEBAR_POSITION_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Left', 'havenlytics'),
  value: 'left'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Right', 'havenlytics'),
  value: 'right'
}];
function cleanTokens(tokens) {
  if (!Array.isArray(tokens)) {
    return [];
  }
  return tokens.map(token => String(token).trim()).filter(token => token.length > 0);
}
function Edit({
  attributes,
  setAttributes
}) {
  const {
    defaultView,
    columns,
    postsPerPage,
    orderby,
    featuredOnly,
    showFilterSidebar,
    sidebarPosition,
    department,
    propertyType,
    status,
    location
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Layout', 'havenlytics'),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Search-first archive. Same filters and cards as Property Archive.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default view', 'havenlytics'),
    value: defaultView,
    options: VIEW_OPTIONS,
    onChange: value => setAttributes({
      defaultView: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Columns', 'havenlytics'),
    value: columns,
    min: 1,
    max: 4,
    onChange: value => setAttributes({
      columns: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Applies to grid view.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show filter sidebar', 'havenlytics'),
    checked: !!showFilterSidebar,
    onChange: value => setAttributes({
      showFilterSidebar: value
    }),
    __nextHasNoMarginBottom: true
  }), showFilterSidebar && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sidebar position', 'havenlytics'),
    value: sidebarPosition,
    options: SIDEBAR_POSITION_OPTIONS,
    onChange: value => setAttributes({
      sidebarPosition: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Query', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Properties per page', 'havenlytics'),
    value: postsPerPage,
    min: 1,
    max: 48,
    onChange: value => setAttributes({
      postsPerPage: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Order by', 'havenlytics'),
    value: orderby,
    options: ORDERBY_OPTIONS,
    onChange: value => setAttributes({
      orderby: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Featured properties only', 'havenlytics'),
    checked: !!featuredOnly,
    onChange: value => setAttributes({
      featuredOnly: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Taxonomy filters', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
    status: "info",
    isDismissible: false
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Enter term slugs to pre-filter results. Visitors can still refine with the search filters.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Department (slug)', 'havenlytics'),
    value: department,
    onChange: value => setAttributes({
      department: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FormTokenField, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Property types (slugs)', 'havenlytics'),
    value: propertyType,
    onChange: tokens => setAttributes({
      propertyType: cleanTokens(tokens)
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FormTokenField, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Status (slugs)', 'havenlytics'),
    value: status,
    onChange: tokens => setAttributes({
      status: cleanTokens(tokens)
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FormTokenField, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Locations (slugs)', 'havenlytics'),
    value: location,
    onChange: tokens => setAttributes({
      location: cleanTokens(tokens)
    })
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
    block: "havenlytics/property-search",
    attributes: attributes
  })));
}

/***/ }),

/***/ "./src/blocks/property-search/index.js":
/*!*********************************************!*\
  !*** ./src/blocks/property-search/index.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/property-search/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/property-search/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * Property Search block registration.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/saved-properties/block.json":
/*!************************************************!*\
  !*** ./src/blocks/saved-properties/block.json ***!
  \************************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"havenlytics/saved-properties","version":"3.5.0","title":"HVN: Saved Properties","category":"havenlytics","icon":"heart","description":"Displays the current user\'s saved (favorited) properties. A presentation layer over the existing Favorites system — reuses the Property Card, query, favorites storage and Authentication block. No new favorites logic.","keywords":["saved","favorites","favourites","wishlist","properties","havenlytics"],"textdomain":"havenlytics","supports":{"html":false,"anchor":true,"align":["wide","full"],"spacing":{"margin":true,"padding":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"background":true,"text":true,"link":true},"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true}},"attributes":{"layout":{"type":"string","default":"grid"},"columns":{"type":"number","default":3},"postsPerPage":{"type":"number","default":12},"orderby":{"type":"string","default":"newest"},"paginationMode":{"type":"string","default":"numbered"},"showTitle":{"type":"boolean","default":true},"sectionTitle":{"type":"string","default":"Saved Properties"},"showDescription":{"type":"boolean","default":false},"sectionDescription":{"type":"string","default":""},"loggedOutMode":{"type":"string","default":"auth"},"emptyButtonText":{"type":"string","default":"Browse Properties"},"visibleSlides":{"type":"number","default":3},"autoplay":{"type":"boolean","default":false},"showNav":{"type":"boolean","default":true},"showDots":{"type":"boolean","default":true}},"editorScript":"hvnly-blocks-editor","editorStyle":"hvnly-blocks-editor","style":"hvnly-block-saved-properties"}');

/***/ }),

/***/ "./src/blocks/saved-properties/edit.js":
/*!*********************************************!*\
  !*** ./src/blocks/saved-properties/edit.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../shared/BlockPreview */ "./src/blocks/shared/BlockPreview.js");

/**
 * HVN: Saved Properties block — editor component.
 *
 * The preview is the real server render (ServerSideRender via BlockPreview), so
 * the editor is WYSIWYG. When the editing user has no saved properties the
 * renderer shows a sample of recent listings so the layout is visible. All
 * inputs are neutralized inside the editor by the shared EditorPreview guard.
 *
 * @package
 * @since   3.5.0
 */





const LAYOUT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Grid', 'havenlytics'),
  value: 'grid'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('List', 'havenlytics'),
  value: 'list'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Compact', 'havenlytics'),
  value: 'compact'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Carousel', 'havenlytics'),
  value: 'carousel'
}];
const COLUMN_OPTIONS = [{
  label: '1',
  value: 1
}, {
  label: '2',
  value: 2
}, {
  label: '3',
  value: 3
}, {
  label: '4',
  value: 4
}, {
  label: '6',
  value: 6
}];
const ORDER_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Newest saved', 'havenlytics'),
  value: 'newest'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Oldest saved', 'havenlytics'),
  value: 'oldest'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Recently updated', 'havenlytics'),
  value: 'recently_updated'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Property title', 'havenlytics'),
  value: 'title'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Price', 'havenlytics'),
  value: 'price'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default', 'havenlytics'),
  value: 'default'
}];
const PAGINATION_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Numbered pages', 'havenlytics'),
  value: 'numbered'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('None (show one page)', 'havenlytics'),
  value: 'none'
}];
const LOGGED_OUT_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Sign-in form (Authentication block)', 'havenlytics'),
  value: 'auth'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Compact sign-in prompt', 'havenlytics'),
  value: 'message'
}];
function Edit({
  attributes,
  setAttributes
}) {
  const {
    layout,
    columns,
    postsPerPage,
    orderby,
    paginationMode,
    showTitle,
    sectionTitle,
    showDescription,
    sectionDescription,
    loggedOutMode,
    emptyButtonText,
    visibleSlides,
    autoplay,
    showNav,
    showDots
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  const isCarousel = layout === 'carousel';
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('General', 'havenlytics'),
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Favorites use the same Property Card Builder as archive listings.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Layout', 'havenlytics'),
    value: layout,
    options: LAYOUT_OPTIONS,
    onChange: value => setAttributes({
      layout: value
    }),
    __nextHasNoMarginBottom: true
  }), !isCarousel && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Columns', 'havenlytics'),
    value: columns,
    options: COLUMN_OPTIONS,
    onChange: value => setAttributes({
      columns: parseInt(value, 10)
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Properties per page', 'havenlytics'),
    value: postsPerPage,
    min: 1,
    max: 48,
    onChange: value => setAttributes({
      postsPerPage: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Ordering', 'havenlytics'),
    value: orderby,
    options: ORDER_OPTIONS,
    onChange: value => setAttributes({
      orderby: value
    }),
    __nextHasNoMarginBottom: true
  }), !isCarousel && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Pagination', 'havenlytics'),
    value: paginationMode,
    options: PAGINATION_OPTIONS,
    onChange: value => setAttributes({
      paginationMode: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Numbered pages reload the page — saved lists are user-specific and are never paged through the shared archive AJAX.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Header', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show section title', 'havenlytics'),
    checked: !!showTitle,
    onChange: value => setAttributes({
      showTitle: value
    }),
    __nextHasNoMarginBottom: true
  }), showTitle && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Section title', 'havenlytics'),
    value: sectionTitle,
    onChange: value => setAttributes({
      sectionTitle: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show section description', 'havenlytics'),
    checked: !!showDescription,
    onChange: value => setAttributes({
      showDescription: value
    }),
    __nextHasNoMarginBottom: true
  }), showDescription && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Section description', 'havenlytics'),
    value: sectionDescription,
    onChange: value => setAttributes({
      sectionDescription: value
    }),
    __nextHasNoMarginBottom: true
  })), isCarousel && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Carousel', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Visible slides', 'havenlytics'),
    value: visibleSlides,
    min: 1,
    max: 5,
    onChange: value => setAttributes({
      visibleSlides: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Autoplay', 'havenlytics'),
    checked: !!autoplay,
    onChange: value => setAttributes({
      autoplay: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show navigation arrows', 'havenlytics'),
    checked: !!showNav,
    onChange: value => setAttributes({
      showNav: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show dots', 'havenlytics'),
    checked: !!showDots,
    onChange: value => setAttributes({
      showDots: value
    }),
    __nextHasNoMarginBottom: true
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Empty & signed-out state', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('When signed out', 'havenlytics'),
    value: loggedOutMode,
    options: LOGGED_OUT_OPTIONS,
    onChange: value => setAttributes({
      loggedOutMode: value
    }),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Reuses the HVN: Authentication block — no separate login form is created.', 'havenlytics'),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Empty-state button text', 'havenlytics'),
    value: emptyButtonText,
    onChange: value => setAttributes({
      emptyButtonText: value
    }),
    __nextHasNoMarginBottom: true
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_shared_BlockPreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
    block: "havenlytics/saved-properties",
    attributes: attributes
  })));
}

/***/ }),

/***/ "./src/blocks/saved-properties/index.js":
/*!**********************************************!*\
  !*** ./src/blocks/saved-properties/index.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./src/blocks/saved-properties/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/saved-properties/edit.js");
/* harmony import */ var _shared_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/icon */ "./src/blocks/shared/icon.js");
/**
 * HVN: Saved Properties block registration.
 *
 * Presentation layer only. The saved/favorited list, storage, REST and card
 * rendering all come from the existing Favorites + Property Card systems — this
 * block adds no new favorites logic.
 *
 * @package
 * @since   3.5.0
 */





(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  icon: _shared_icon__WEBPACK_IMPORTED_MODULE_3__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/blocks/shared/BlockPreview.js":
/*!*******************************************!*\
  !*** ./src/blocks/shared/BlockPreview.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ BlockPreview)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/server-side-render */ "@wordpress/server-side-render");
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _EditorPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./EditorPreview */ "./src/blocks/shared/EditorPreview.js");
/* harmony import */ var _HvnlyPlaceholder__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./HvnlyPlaceholder */ "./src/blocks/shared/HvnlyPlaceholder.js");

/**
 * Shared editor preview for every Havenlytics block.
 *
 * Combines the three editor-only concerns in one place so all blocks behave
 * consistently:
 *   1. ServerSideRender — the real frontend render (WYSIWYG).
 *   2. EditorPreview — cancels navigation/submits inside the editor.
 *   3. HvnlyPlaceholder — branded placeholder instead of "No Preview" for the
 *      empty / loading / error states.
 *
 * Title and description are read from the registered block type, so callers only
 * pass the block name and attributes.
 *
 * @package
 * @since   3.5.0
 */







/**
 * @param {Object} props
 * @param {string} props.block      Registered block name.
 * @param {Object} props.attributes Block attributes.
 * @return {Element} Guarded, branded server-side preview.
 */
function BlockPreview({
  block,
  attributes
}) {
  const blockType = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => select(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__.store).getBlockType(block), [block]);
  const placeholder = () => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_HvnlyPlaceholder__WEBPACK_IMPORTED_MODULE_5__["default"], {
    title: blockType?.title,
    description: blockType?.description
  });

  // Errors must stay visible: render the real SSR error message instead of
  // masking it behind the branded placeholder.
  const errorPlaceholder = ({
    response
  }) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-block-editor-preview__error"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_HvnlyPlaceholder__WEBPACK_IMPORTED_MODULE_5__["default"], {
    title: blockType?.title,
    description: response?.errorMsg || 'The preview could not be rendered. Check the browser console and PHP error log.'
  }));
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_EditorPreview__WEBPACK_IMPORTED_MODULE_4__["default"], null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_3___default()), {
    block: block,
    attributes: attributes,
    EmptyResponsePlaceholder: placeholder,
    LoadingResponsePlaceholder: placeholder,
    ErrorResponsePlaceholder: errorPlaceholder
  }));
}

/***/ }),

/***/ "./src/blocks/shared/EditorPreview.js":
/*!********************************************!*\
  !*** ./src/blocks/shared/EditorPreview.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ EditorPreview)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);

/**
 * Editor-only preview guard + controller boot.
 *
 * Wraps a block's ServerSideRender output and:
 *   1. Neutralizes navigation inside the block editor.
 *   2. Boots carousel / map controllers when SSR markup appears.
 *
 * Retry budget is absolute while a map/carousel stays pending: the
 * MutationObserver must not reset attempts (Leaflet mutates the canvas
 * heavily). After a successful ready state, attempts reset so a later SSR
 * refresh can boot again.
 *
 * @package
 * @since   3.5.0
 */


const MAX_BOOT_ATTEMPTS = 20;
const BOOT_INTERVAL_MS = 100;

/**
 * @param {HTMLElement|null} node Preview root.
 */
function bootControllers(node) {
  if (!node || typeof window === 'undefined') {
    return;
  }

  // Iframed editor (WP 6.3+): the SSR markup lives in the iframe document,
  // and enqueue_block_assets loads the controllers into BOTH windows. Prefer
  // the copy that shares the node's document — its `document`/`L` references
  // point at the right DOM. Fall back to the parent copy (non-iframed
  // editors) which resolves the node's ownerDocument internally.
  const frameWin = node.ownerDocument ? node.ownerDocument.defaultView : null;
  const wins = frameWin && frameWin !== window ? [frameWin, window] : [window];
  for (const win of wins) {
    if (typeof win.hvnlyInitBlockCarousels === 'function') {
      win.hvnlyInitBlockCarousels(node);
    }
    if (typeof win.hvnlyInitBlockMaps === 'function') {
      win.hvnlyInitBlockMaps(node);
    }
  }
}

/**
 * @param {HTMLElement} node Preview root.
 * @return {boolean} Whether map/carousel markup still needs booting.
 */
function hasPendingControllers(node) {
  return !!(node.querySelector('[data-hvnly-block-map]:not([data-hvnly-block-map-ready="1"])') || node.querySelector('[data-hvnly-block-carousel]:not([data-hvnly-block-carousel-ready="1"])'));
}

/**
 * @param {Object}  props
 * @param {Element} props.children The preview content (e.g. ServerSideRender).
 * @return {Element} Wrapped, interaction-guarded preview.
 */
function EditorPreview({
  children
}) {
  const ref = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    const node = ref.current;
    if (!node) {
      return undefined;
    }
    const preventNavigation = event => {
      const interactive = event.target && event.target.closest ? event.target.closest('a[href], button, input[type="submit"], input[type="button"], [role="button"]') : null;
      if (interactive) {
        event.preventDefault();
      }
    };
    const preventSubmit = event => event.preventDefault();
    node.addEventListener('click', preventNavigation, true);
    node.addEventListener('submit', preventSubmit, true);
    let bootTimer = null;
    let attempts = 0;
    let stopped = false;
    let observer = null;
    const stopRetries = () => {
      stopped = true;
      window.clearTimeout(bootTimer);
      bootTimer = null;
      if (observer) {
        observer.disconnect();
      }
    };
    const scheduleBoot = () => {
      if (stopped) {
        return;
      }
      window.clearTimeout(bootTimer);
      bootTimer = window.setTimeout(() => {
        bootControllers(node);
        if (!hasPendingControllers(node)) {
          // Ready (or no controllers). Allow a later SSR refresh to retry.
          attempts = 0;
          return;
        }
        attempts += 1;
        if (attempts >= MAX_BOOT_ATTEMPTS) {
          stopRetries();
          return;
        }
        scheduleBoot();
      }, BOOT_INTERVAL_MS);
    };
    scheduleBoot();
    observer = new window.MutationObserver(() => {
      if (stopped || !hasPendingControllers(node)) {
        return;
      }
      // Never reset attempts while still pending — that caused endless loops.
      scheduleBoot();
    });
    observer.observe(node, {
      childList: true,
      subtree: true
    });
    return () => {
      stopRetries();
      node.removeEventListener('click', preventNavigation, true);
      node.removeEventListener('submit', preventSubmit, true);
    };
  }, []);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ref: ref,
    className: "hvnly-block-editor-preview"
  }, children);
}

/***/ }),

/***/ "./src/blocks/shared/EntitySearchControl.js":
/*!**************************************************!*\
  !*** ./src/blocks/shared/EntitySearchControl.js ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ EntitySearchControl)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_5__);

/**
 * Async browse + search entity picker for Havenlytics CPT posts.
 *
 * Opens with the first page of published posts (no typing required). Optional
 * search filters via the existing wp/v2 REST endpoint. IDs are stored
 * internally only — never shown in the UI.
 *
 * @package
 * @since   3.5.0
 */






const PER_PAGE = 20;
const SEARCH_DEBOUNCE_MS = 300;

/**
 * Decode a common HTML entity string from REST title.rendered.
 *
 * @param {string} html Raw HTML title.
 * @return {string} Plain text.
 */
function decodeTitle(html) {
  if (!html) {
    return '';
  }
  const el = document.createElement('textarea');
  el.innerHTML = html;
  return el.value;
}

/**
 * Human label when title/name is missing — never expose numeric IDs.
 *
 * @param {string} postType Post type slug.
 * @return {string} Fallback label.
 */
function untitledLabel(postType) {
  return postType === 'hvnly_agent' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Untitled agent', 'havenlytics') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Untitled property', 'havenlytics');
}

/**
 * Coerce a value to a plain search string for REST. Never serialize functions.
 *
 * @param {*} value Candidate search value.
 * @return {string} Safe search string (may be empty).
 */
function toSearchString(value) {
  return typeof value === 'string' ? value.trim() : '';
}

/**
 * Normalize a REST post into a picker item.
 *
 * @param {Object} post     REST post.
 * @param {string} postType Post type slug.
 * @return {Object} Normalized item.
 */
function mapPost(post, postType) {
  const id = Number(post?.id) || 0;
  const picker = post?.hvnly_picker && typeof post.hvnly_picker === 'object' ? post.hvnly_picker : {};
  const fallback = untitledLabel(postType);
  if (postType === 'hvnly_agent') {
    const name = picker.name || decodeTitle(post?.title?.rendered) || fallback;
    return {
      id,
      label: name,
      kind: 'agent',
      name,
      avatar: picker.avatar || '',
      agency: picker.agency || '',
      propertyCount: Number(picker.property_count) || 0,
      email: picker.email || ''
    };
  }
  const title = picker.title || decodeTitle(post?.title?.rendered) || fallback;
  return {
    id,
    label: title,
    kind: 'property',
    title,
    thumbnail: picker.thumbnail || '',
    price: picker.price || '',
    status: picker.status || '',
    location: picker.location || '',
    mls: picker.mls || ''
  };
}

/**
 * @param {Object}   props
 * @param {string}   props.postType     REST post type slug (hvnly_property|hvnly_agent).
 * @param {string}   props.label        Control label.
 * @param {boolean}  [props.multiple]   Allow multiple selections.
 * @param {number|number[]} props.value Selected id(s).
 * @param {Function} props.onChange     Change handler.
 * @param {string}   [props.help]       Help text.
 * @param {string}   [props.placeholder] Search placeholder.
 * @return {Element} Control.
 */
function EntitySearchControl({
  postType,
  label,
  multiple = false,
  value,
  onChange,
  help,
  placeholder
}) {
  const [search, setSearch] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)('');
  const [debouncedQuery, setDebouncedQuery] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)('');
  const [items, setItems] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)([]);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(false);
  const [page, setPage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(1);
  const [hasMore, setHasMore] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(false);
  const [selectedMap, setSelectedMap] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)({});
  const [browsing, setBrowsing] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(false);
  const listRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useRef)(null);
  const fetchGen = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useRef)(0);
  const queryRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useRef)('');
  const selectedIds = multiple ? Array.isArray(value) ? value.map(Number).filter(Boolean) : [] : [];
  const singleId = !multiple ? Number(value) || 0 : 0;
  const singleSelected = singleId ? selectedMap[String(singleId)] : null;

  // Browse list is always open for multi-select; for single, only while choosing.
  const showBrowse = multiple || browsing || !singleId;

  // Debounce the search *string* — do not use @wordpress/compose useDebounce
  // here (that returns a debounced function, not a value).
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const next = toSearchString(search);
    const timer = window.setTimeout(() => {
      setDebouncedQuery(next);
    }, SEARCH_DEBOUNCE_MS);
    return () => window.clearTimeout(timer);
  }, [search]);
  const fetchPage = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useCallback)(async (query, pageNum, append) => {
    const searchQuery = toSearchString(query);
    queryRef.current = searchQuery;
    const gen = ++fetchGen.current;
    setLoading(true);
    try {
      const args = {
        per_page: PER_PAGE,
        page: pageNum,
        status: 'publish',
        orderby: searchQuery ? 'relevance' : 'title',
        order: 'asc',
        _fields: 'id,title,hvnly_picker'
      };
      // Only attach search when the user typed a string filter.
      if (searchQuery) {
        args.search = searchQuery;
      }
      const path = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_5__.addQueryArgs)(`/wp/v2/${postType}`, args);
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path,
        parse: false
      });
      if (gen !== fetchGen.current) {
        return;
      }
      const totalPages = parseInt(response.headers.get('X-WP-TotalPages') || '1', 10);
      const data = await response.json();
      const mapped = (Array.isArray(data) ? data : []).map(post => mapPost(post, postType));
      setItems(prev => append ? [...prev, ...mapped] : mapped);
      setHasMore(pageNum < totalPages);
      setPage(pageNum);
      setSelectedMap(prev => {
        const next = {
          ...prev
        };
        mapped.forEach(item => {
          next[String(item.id)] = item;
        });
        return next;
      });
    } catch (e) {
      if (gen !== fetchGen.current) {
        return;
      }
      if (!append) {
        setItems([]);
      }
      setHasMore(false);
    } finally {
      if (gen === fetchGen.current) {
        setLoading(false);
      }
    }
  }, [postType]);

  // Immediately load first page when the browse list is shown; re-fetch when
  // the debounced search *string* changes (empty = browse all published).
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (!showBrowse) {
      return;
    }
    fetchPage(debouncedQuery, 1, false);
  }, [showBrowse, debouncedQuery, fetchPage]);

  // Resolve labels for already-selected ids (edit load).
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const ids = multiple ? selectedIds : singleId ? [singleId] : [];
    const missing = ids.filter(id => !selectedMap[String(id)]);
    if (!missing.length) {
      return;
    }
    let cancelled = false;
    (async () => {
      const next = {
        ...selectedMap
      };
      await Promise.all(missing.map(async id => {
        try {
          const post = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
            path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_5__.addQueryArgs)(`/wp/v2/${postType}/${id}`, {
              _fields: 'id,title,hvnly_picker'
            })
          });
          next[String(id)] = mapPost(post, postType);
        } catch (e) {
          const fallback = untitledLabel(postType);
          next[String(id)] = {
            id,
            label: fallback,
            kind: postType === 'hvnly_agent' ? 'agent' : 'property',
            title: fallback,
            name: fallback
          };
        }
      }));
      if (!cancelled) {
        setSelectedMap(next);
      }
    })();
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [multiple ? selectedIds.join(',') : singleId, postType]);

  // Infinite scroll: load next page near the bottom of the list.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (!showBrowse || !hasMore || loading) {
      return undefined;
    }
    const el = listRef.current;
    if (!el) {
      return undefined;
    }
    const onScroll = () => {
      if (el.scrollTop + el.clientHeight >= el.scrollHeight - 48) {
        fetchPage(queryRef.current, page + 1, true);
      }
    };
    el.addEventListener('scroll', onScroll);
    return () => el.removeEventListener('scroll', onScroll);
  }, [showBrowse, hasMore, loading, page, fetchPage]);
  const toggleItem = item => {
    const id = Number(item?.id) || 0;
    if (!id) {
      return;
    }
    setSelectedMap(prev => ({
      ...prev,
      [String(id)]: item
    }));
    if (multiple) {
      if (selectedIds.includes(id)) {
        onChange(selectedIds.filter(x => x !== id));
      } else {
        onChange([...selectedIds, id]);
      }
      return;
    }
    onChange(id);
    setBrowsing(false);
    setSearch('');
  };
  const removeId = id => {
    if (multiple) {
      onChange(selectedIds.filter(x => x !== id));
    } else {
      onChange(0);
      setBrowsing(true);
    }
  };
  const startChange = () => {
    setBrowsing(true);
    setSearch('');
  };
  const searchPlaceholder = placeholder || (postType === 'hvnly_agent' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Filter agents (optional)…', 'havenlytics') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Filter by title, address, or MLS (optional)…', 'havenlytics'));
  const emptyLabel = debouncedQuery ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No matches found.', 'havenlytics') : postType === 'hvnly_agent' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No published agents found.', 'havenlytics') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No published properties found.', 'havenlytics');
  const loadingLabel = debouncedQuery ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Searching…', 'havenlytics') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Loading…', 'havenlytics');
  const renderPropertyRow = item => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-entity-search__row hvnly-entity-search__row--property"
  }, item.thumbnail ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    className: "hvnly-entity-search__thumb",
    src: item.thumbnail,
    alt: ""
  }) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-entity-search__thumb hvnly-entity-search__thumb--empty"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-entity-search__meta"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", {
    className: "hvnly-entity-search__title"
  }, item.title), item.location ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-entity-search__line"
  }, item.location) : null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-entity-search__line hvnly-entity-search__line--muted"
  }, [item.price, item.status].filter(Boolean).join(' · '))));
  const renderAgentRow = item => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-entity-search__row hvnly-entity-search__row--agent"
  }, item.avatar ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    className: "hvnly-entity-search__avatar",
    src: item.avatar,
    alt: ""
  }) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-entity-search__avatar hvnly-entity-search__avatar--empty"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-entity-search__meta"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", {
    className: "hvnly-entity-search__title"
  }, item.name), item.agency ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-entity-search__line"
  }, item.agency) : null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-entity-search__line hvnly-entity-search__line--muted"
  }, [item.propertyCount > 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(/* translators: %d: listing count */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('%d Listings', 'havenlytics'), item.propertyCount) : '', item.email || ''].filter(Boolean).join(' · '))));
  const renderItem = item => item.kind === 'agent' ? renderAgentRow(item) : renderPropertyRow(item);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-entity-search"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.BaseControl, {
    label: label,
    help: help,
    __nextHasNoMarginBottom: true
  }, !multiple && singleSelected && !browsing ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-entity-search__chosen"
  }, renderItem(singleSelected), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
    size: "small",
    variant: "tertiary",
    onClick: startChange
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Change', 'havenlytics'))) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SearchControl, {
    label: label,
    hideLabelFromVision: true,
    value: search,
    onChange: next => setSearch(typeof next === 'string' ? next : ''),
    placeholder: searchPlaceholder,
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-entity-search__dropdown",
    role: "listbox",
    ref: listRef
  }, loading && items.length === 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "hvnly-entity-search__status"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Spinner, null), " ", loadingLabel), !loading && items.length === 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "hvnly-entity-search__status"
  }, emptyLabel), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("ul", {
    className: "hvnly-entity-search__results"
  }, items.map(item => {
    const selected = multiple ? selectedIds.includes(item.id) : singleId === item.id;
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", {
      key: item.id
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
      type: "button",
      className: 'hvnly-entity-search__option' + (selected ? ' is-selected' : ''),
      "aria-selected": selected,
      onClick: () => toggleItem(item)
    }, multiple && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      className: "hvnly-entity-search__check",
      "aria-hidden": "true"
    }, selected ? '✓' : ''), renderItem(item)));
  })), loading && items.length > 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "hvnly-entity-search__status"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Spinner, null)), hasMore && !loading && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-entity-search__more"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
    variant: "secondary",
    onClick: () => fetchPage(queryRef.current, page + 1, true)
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Load more', 'havenlytics')))), !multiple && browsing && singleId > 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
    size: "small",
    variant: "link",
    onClick: () => {
      setBrowsing(false);
      setSearch('');
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Cancel', 'havenlytics')))), multiple && selectedIds.length > 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("ul", {
    className: "hvnly-entity-search__chips"
  }, selectedIds.map(id => {
    const item = selectedMap[String(id)];
    const chipLabel = item?.label || untitledLabel(postType);
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", {
      key: id
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      className: "hvnly-entity-search__chip-check",
      "aria-hidden": "true"
    }, "\u2713"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      className: "hvnly-entity-search__chip-label"
    }, chipLabel), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
      size: "small",
      variant: "tertiary",
      className: "hvnly-entity-search__chip-remove",
      onClick: () => removeId(id),
      "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Remove', 'havenlytics')
    }, "\xD7"));
  })));
}

/***/ }),

/***/ "./src/blocks/shared/HvnlyPlaceholder.js":
/*!***********************************************!*\
  !*** ./src/blocks/shared/HvnlyPlaceholder.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ HvnlyPlaceholder)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _icon__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./icon */ "./src/blocks/shared/icon.js");

/**
 * Branded preview placeholder shown instead of Gutenberg's "No Preview".
 *
 * Displays the Havenlytics logo, the block title and a short description so a
 * block that has not yet produced a preview still looks like an official
 * Havenlytics feature.
 *
 * @package
 * @since   3.5.0
 */




/**
 * @param {Object} props
 * @param {string} props.title       Block title.
 * @param {string} props.description Short block description.
 * @return {Element} Placeholder element.
 */
function HvnlyPlaceholder({
  title,
  description
}) {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "hvnly-block-placeholder"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-block-placeholder__logo"
  }, _icon__WEBPACK_IMPORTED_MODULE_2__["default"]), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-block-placeholder__title"
  }, title || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Havenlytics block', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-block-placeholder__desc"
  }, description || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Preview will appear after configuring the block.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "hvnly-block-placeholder__hint"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Configure block settings in the sidebar.', 'havenlytics')));
}

/***/ }),

/***/ "./src/blocks/shared/PeopleArchiveEdit.js":
/*!************************************************!*\
  !*** ./src/blocks/shared/PeopleArchiveEdit.js ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ createPeopleArchiveEdit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _BlockPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./BlockPreview */ "./src/blocks/shared/BlockPreview.js");

/**
 * Shared editor component for people-archive blocks (Agents, Agency).
 *
 * Both blocks expose the same control surface — display header, search, view
 * controls, columns, per-page and ordering — over a live server-rendered
 * preview. Only the block name and order-by options differ.
 *
 * @package
 * @since   3.5.0
 */





const VIEW_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Grid', 'havenlytics'),
  value: 'grid'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('List', 'havenlytics'),
  value: 'list'
}];
const ORDER_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Ascending', 'havenlytics'),
  value: 'ASC'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Descending', 'havenlytics'),
  value: 'DESC'
}];

/**
 * Build a people-archive edit component bound to a specific block.
 *
 * @param {string} blockName      Registered block name (e.g. havenlytics/agents).
 * @param {Array}  orderbyOptions SelectControl options for the order-by field.
 * @return {Function} A Gutenberg edit component.
 */
function createPeopleArchiveEdit(blockName, orderbyOptions) {
  return function Edit({
    attributes,
    setAttributes
  }) {
    const {
      defaultView,
      columns,
      postsPerPage,
      orderby,
      order,
      showHeader,
      title,
      subtitle,
      showSearch,
      showViewControls
    } = attributes;
    const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Layout', 'havenlytics'),
      initialOpen: true
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
      className: "components-base-control__help"
    }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Controls the archive layout visitors see first.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Default view', 'havenlytics'),
      value: defaultView,
      options: VIEW_OPTIONS,
      onChange: value => setAttributes({
        defaultView: value
      }),
      __nextHasNoMarginBottom: true
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Columns', 'havenlytics'),
      value: columns,
      min: 1,
      max: 4,
      onChange: value => setAttributes({
        columns: value
      }),
      help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Used in grid view.', 'havenlytics'),
      __nextHasNoMarginBottom: true
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.RangeControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Items per page', 'havenlytics'),
      value: postsPerPage,
      min: 1,
      max: 48,
      onChange: value => setAttributes({
        postsPerPage: value
      }),
      __nextHasNoMarginBottom: true
    })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Header', 'havenlytics'),
      initialOpen: false
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show header', 'havenlytics'),
      checked: !!showHeader,
      onChange: value => setAttributes({
        showHeader: value
      }),
      __nextHasNoMarginBottom: true
    }), showHeader && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title', 'havenlytics'),
      value: title,
      onChange: value => setAttributes({
        title: value
      }),
      __nextHasNoMarginBottom: true
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Subtitle', 'havenlytics'),
      value: subtitle,
      onChange: value => setAttributes({
        subtitle: value
      }),
      __nextHasNoMarginBottom: true
    }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Query', 'havenlytics'),
      initialOpen: false
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Order by', 'havenlytics'),
      value: orderby,
      options: orderbyOptions,
      onChange: value => setAttributes({
        orderby: value
      }),
      __nextHasNoMarginBottom: true
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Order', 'havenlytics'),
      value: order,
      options: ORDER_OPTIONS,
      onChange: value => setAttributes({
        order: value
      }),
      __nextHasNoMarginBottom: true
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show search', 'havenlytics'),
      checked: !!showSearch,
      onChange: value => setAttributes({
        showSearch: value
      }),
      __nextHasNoMarginBottom: true
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show view controls', 'havenlytics'),
      checked: !!showViewControls,
      onChange: value => setAttributes({
        showViewControls: value
      }),
      __nextHasNoMarginBottom: true
    }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      ...blockProps
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_BlockPreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
      block: blockName,
      attributes: attributes
    })));
  };
}

/***/ }),

/***/ "./src/blocks/shared/SectionHeaderPanel.js":
/*!*************************************************!*\
  !*** ./src/blocks/shared/SectionHeaderPanel.js ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SectionHeaderPanel)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);

/**
 * Shared "Section header" inspector panel for premium blocks.
 *
 * @package
 * @since   3.5.0
 */



const ALIGN_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Left', 'havenlytics'),
  value: 'left'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Center', 'havenlytics'),
  value: 'center'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Right', 'havenlytics'),
  value: 'right'
}];
const TARGET_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Same tab', 'havenlytics'),
  value: '_self'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('New tab', 'havenlytics'),
  value: '_blank'
}];
function SectionHeaderPanel({
  attributes,
  setAttributes
}) {
  const {
    showHeader,
    sectionSubtitle,
    sectionTitle,
    sectionDescription,
    sectionAlign,
    sectionButtonShow,
    sectionButtonText,
    sectionButtonUrl,
    sectionButtonTarget
  } = attributes;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Section header', 'havenlytics'),
    initialOpen: false
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "components-base-control__help"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Optional heading above the listings. Leave empty fields blank to hide them.', 'havenlytics')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show header', 'havenlytics'),
    checked: showHeader !== false,
    onChange: value => setAttributes({
      showHeader: value
    }),
    __nextHasNoMarginBottom: true
  }), showHeader !== false && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Subtitle', 'havenlytics'),
    value: sectionSubtitle || '',
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Optional eyebrow', 'havenlytics'),
    onChange: value => setAttributes({
      sectionSubtitle: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Title', 'havenlytics'),
    value: sectionTitle || '',
    onChange: value => setAttributes({
      sectionTitle: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Description', 'havenlytics'),
    value: sectionDescription || '',
    rows: 3,
    onChange: value => setAttributes({
      sectionDescription: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Alignment', 'havenlytics'),
    value: sectionAlign || 'left',
    options: ALIGN_OPTIONS,
    onChange: value => setAttributes({
      sectionAlign: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Show button', 'havenlytics'),
    checked: !!sectionButtonShow,
    onChange: value => setAttributes({
      sectionButtonShow: value
    }),
    __nextHasNoMarginBottom: true
  }), !!sectionButtonShow && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Button text', 'havenlytics'),
    value: sectionButtonText || '',
    onChange: value => setAttributes({
      sectionButtonText: value
    }),
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Button URL', 'havenlytics'),
    value: sectionButtonUrl || '',
    onChange: value => setAttributes({
      sectionButtonUrl: value
    }),
    type: "url",
    __nextHasNoMarginBottom: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Open link in', 'havenlytics'),
    value: sectionButtonTarget || '_self',
    options: TARGET_OPTIONS,
    onChange: value => setAttributes({
      sectionButtonTarget: value
    }),
    __nextHasNoMarginBottom: true
  }))));
}

/***/ }),

/***/ "./src/blocks/shared/icon.js":
/*!***********************************!*\
  !*** ./src/blocks/shared/icon.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);

/**
 * Havenlytics brand icon (shared across all blocks).
 *
 * This is the same mark the Elementor widgets display in their panel
 * (assets/admin/img/havenlytics-icon.svg), so the Gutenberg inserter shows the
 * identical Havenlytics identity. Passed to registerBlockType( name, { icon } ),
 * which overrides the block.json dashicon fallback in the editor.
 *
 * @package
 * @since   3.5.0
 */

/* eslint-disable react/no-unknown-property */

const HvnlyIcon = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
  width: "24",
  height: "24",
  viewBox: "0 0 118 118",
  fill: "none",
  xmlns: "http://www.w3.org/2000/svg",
  "aria-hidden": "true",
  focusable: "false"
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("g", {
  clipPath: "url(#hvnly_icon_clip)"
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
  d: "M98.6652 -0.221252H19.5561C8.6288 -0.221252 -0.221191 8.64105 -0.221191 19.5683V98.6652C-0.221191 109.592 8.6288 118.455 19.5561 118.455H98.6652C109.593 118.455 118.455 109.592 118.455 98.6652V19.5683C118.455 8.64105 109.593 -0.221252 98.6652 -0.221252ZM108.56 57.7954C105.979 57.484 103.381 57.3242 100.767 57.316C98.7103 57.316 96.674 57.4144 94.6582 57.611C87.4553 58.2994 80.5596 60.18 74.2048 63.0685C59.295 69.8535 47.3475 82.1943 41.1771 97.7064H19.5561C27.4228 70.6893 48.1588 49.7444 74.2048 41.1402V33.04V20.5394H94.6582V29.3525V37.0962C96.674 36.9405 98.7103 36.8627 100.767 36.8627C103.373 36.8709 105.971 36.9979 108.56 37.2437V57.7954ZM94.6582 97.141H74.2048V71.6727C80.4367 68.3416 87.3569 66.166 94.6582 65.3794V97.141ZM20.9451 20.5148H41.3984V50.7154C33.2859 57.525 26.3411 65.7235 20.9451 75.0037V20.5148Z",
  fill: "url(#hvnly_icon_gradient)"
})), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("defs", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("linearGradient", {
  id: "hvnly_icon_gradient",
  x1: "-0.227339",
  y1: "118.458",
  x2: "-0.227339",
  y2: "-0.221252",
  gradientUnits: "userSpaceOnUse"
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("stop", {
  offset: "0.0673077",
  stopColor: "#6C60FE"
}), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("stop", {
  offset: "1",
  stopColor: "#8A7BFF"
})), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("clipPath", {
  id: "hvnly_icon_clip"
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("rect", {
  width: "118",
  height: "118",
  fill: "white"
}))));
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (HvnlyIcon);

/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/server-side-render":
/*!******************************************!*\
  !*** external ["wp","serverSideRender"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wp"]["serverSideRender"];

/***/ }),

/***/ "@wordpress/url":
/*!*****************************!*\
  !*** external ["wp","url"] ***!
  \*****************************/
/***/ ((module) => {

module.exports = window["wp"]["url"];

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!******************************!*\
  !*** ./src/blocks/blocks.js ***!
  \******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./editor.scss */ "./src/blocks/editor.scss");
/* harmony import */ var _property_archive__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./property-archive */ "./src/blocks/property-archive/index.js");
/* harmony import */ var _property_search__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./property-search */ "./src/blocks/property-search/index.js");
/* harmony import */ var _agents__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./agents */ "./src/blocks/agents/index.js");
/* harmony import */ var _agency__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./agency */ "./src/blocks/agency/index.js");
/* harmony import */ var _featured_properties__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./featured-properties */ "./src/blocks/featured-properties/index.js");
/* harmony import */ var _property_carousel__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./property-carousel */ "./src/blocks/property-carousel/index.js");
/* harmony import */ var _property_map__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./property-map */ "./src/blocks/property-map/index.js");
/* harmony import */ var _authentication__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./authentication */ "./src/blocks/authentication/index.js");
/* harmony import */ var _dashboard__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./dashboard */ "./src/blocks/dashboard/index.js");
/* harmony import */ var _saved_properties__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./saved-properties */ "./src/blocks/saved-properties/index.js");
/* harmony import */ var _property_inquiry__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./property-inquiry */ "./src/blocks/property-inquiry/index.js");
/**
 * Havenlytics blocks — single editor bundle.
 *
 * Registers every Havenlytics block from one editor script so the block-editor
 * dependencies (wp-block-editor, wp-components, …) load once. Frontend markup is
 * produced server-side by each block's PHP render callback.
 *
 * @package
 * @since   3.5.0
 */













})();

/******/ })()
;
//# sourceMappingURL=0.js.map