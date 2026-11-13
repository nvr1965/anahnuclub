import{c as s,a6 as x,u as o,j as e,g as n,d as u}from"./app-Bm_Fje46.js";import{S as y,H as r}from"./layout-ynvwNN6K.js";import{bA as k}from"./club-layout-ox7OZQWb.js";import{A as g}from"./app-layout-Cu0-v5S-.js";/* empty css            */import"./button-BccVE1Th.js";import"./share-2-D9i8AAkM.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const b=[["rect",{width:"20",height:"14",x:"2",y:"3",rx:"2",key:"48i651"}],["line",{x1:"8",x2:"16",y1:"21",y2:"21",key:"1svkeh"}],["line",{x1:"12",x2:"12",y1:"17",y2:"21",key:"vw1qmm"}]],j=s("Monitor",b);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const f=[["path",{d:"M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z",key:"a7tn18"}]],v=s("Moon",f);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const A=[["circle",{cx:"12",cy:"12",r:"4",key:"4exip2"}],["path",{d:"M12 2v2",key:"tus03m"}],["path",{d:"M12 20v2",key:"1lh1kg"}],["path",{d:"m4.93 4.93 1.41 1.41",key:"149t6j"}],["path",{d:"m17.66 17.66 1.41 1.41",key:"ptbguv"}],["path",{d:"M2 12h2",key:"1t8f8n"}],["path",{d:"M20 12h2",key:"1q8mjw"}],["path",{d:"m6.34 17.66-1.41 1.41",key:"1m8zz5"}],["path",{d:"m19.07 4.93-1.41 1.41",key:"1shlcs"}]],M=s("Sun",A);function N({className:t="",...c}){const{appearance:i,updateAppearance:p}=x(),l=o(),d=[{value:"light",icon:M,label:"Light"},{value:"dark",icon:v,label:"Dark"},{value:"system",icon:j,label:"System"}];return e.jsx("div",{className:n("inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800",t),...c,children:d.map(({value:a,icon:m,label:h})=>e.jsxs("button",{onClick:()=>p(a),className:n("flex items-center rounded-md px-3.5 py-1.5 transition-colors",i===a?"bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100":"text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60"),children:[e.jsx(m,{className:"-ms-1 h-4 w-4"}),e.jsx("span",{className:"ms-1.5 text-sm",children:l(h)})]},a))})}const w=[{title:"Appearance settings",href:"/settings/appearance"}];function E(){const t=o();return e.jsxs(g,{breadcrumbs:w,children:[e.jsx(u,{title:t("Appearance settings")}),e.jsxs(y,{children:[e.jsxs("div",{className:"space-y-6",children:[e.jsx(r,{title:t("Appearance settings"),description:t("Update your account's appearance settings")}),e.jsx(N,{})]}),e.jsxs("div",{className:"space-y-6",children:[e.jsx(r,{title:t("Interface style"),description:t("How the feed, the top bar and profiles look. Everything works the same in every style.")}),e.jsx(k,{})]})]})]})}export{E as default};
