import{c as n,a1 as h,u as r,j as e,g as s,d as u}from"./app-B0q2epDA.js";import{S as x,H as y}from"./layout-CmXysLbn.js";import{A as k}from"./app-layout-B5aU3sT7.js";/* empty css            */import"./button-Ft1PprCg.js";import"./club-layout-MQCWRaZs.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const g=[["rect",{width:"20",height:"14",x:"2",y:"3",rx:"2",key:"48i651"}],["line",{x1:"8",x2:"16",y1:"21",y2:"21",key:"1svkeh"}],["line",{x1:"12",x2:"12",y1:"17",y2:"21",key:"vw1qmm"}]],b=n("Monitor",g);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const j=[["path",{d:"M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z",key:"a7tn18"}]],v=n("Moon",j);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const f=[["circle",{cx:"12",cy:"12",r:"4",key:"4exip2"}],["path",{d:"M12 2v2",key:"tus03m"}],["path",{d:"M12 20v2",key:"1lh1kg"}],["path",{d:"m4.93 4.93 1.41 1.41",key:"149t6j"}],["path",{d:"m17.66 17.66 1.41 1.41",key:"ptbguv"}],["path",{d:"M2 12h2",key:"1t8f8n"}],["path",{d:"M20 12h2",key:"1q8mjw"}],["path",{d:"m6.34 17.66-1.41 1.41",key:"1m8zz5"}],["path",{d:"m19.07 4.93-1.41 1.41",key:"1shlcs"}]],A=n("Sun",f);function M({className:t="",...c}){const{appearance:o,updateAppearance:i}=h(),p=r(),l=[{value:"light",icon:A,label:"Light"},{value:"dark",icon:v,label:"Dark"},{value:"system",icon:b,label:"System"}];return e.jsx("div",{className:s("inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800",t),...c,children:l.map(({value:a,icon:d,label:m})=>e.jsxs("button",{onClick:()=>i(a),className:s("flex items-center rounded-md px-3.5 py-1.5 transition-colors",o===a?"bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100":"text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60"),children:[e.jsx(d,{className:"-ms-1 h-4 w-4"}),e.jsx("span",{className:"ms-1.5 text-sm",children:p(m)})]},a))})}const N=[{title:"Appearance settings",href:"/settings/appearance"}];function z(){const t=r();return e.jsxs(k,{breadcrumbs:N,children:[e.jsx(u,{title:t("Appearance settings")}),e.jsx(x,{children:e.jsxs("div",{className:"space-y-6",children:[e.jsx(y,{title:t("Appearance settings"),description:t("Update your account's appearance settings")}),e.jsx(M,{})]})})]})}export{z as default};
