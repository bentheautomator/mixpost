import{r as _,o as k,k as v,w as e,b as a,a as o,d as l,O as b}from"./app.587cfedd.js";import{u as g}from"./useNotifications.e3b66c27.js";import{_ as x}from"./Panel.30ee1f7d.js";import{_ as n}from"./Input.b6cedb4b.js";import{F as h}from"./Facebook.afbc5b5e.js";import{_ as w}from"./PrimaryButton.2f15e706.js";import{H as m}from"./HorizontalGroup.913bc707.js";import{_ as p}from"./Error.3b7ea2f5.js";import{_ as V}from"./ReadDocHelp.d12e69a3.js";const y={class:"flex items-center"},F={class:"mr-xs"},$=a("span",null,"Facebook",-1),O=a("p",null,[a("a",{href:"https://developers.facebook.com/apps",class:"link",target:"_blank"},"Create an App on Facebook"),l('. Select "Business" for the type of application. ')],-1),j={__name:"FacebookServiceForm",props:{form:{required:!0,type:Object}},setup(t){const f=t,{notify:u}=g(),s=_({}),d=()=>{s.value={},b.put(route("mixpost.services.update",{service:"facebook"}),f.form,{preserveScroll:!0,onSuccess(){u("success","Facebook credentials have been saved")},onError:c=>{s.value=c}})};return(c,r)=>(k(),v(x,{class:"mt-lg"},{title:e(()=>[a("div",y,[a("span",F,[o(h,{class:"text-facebook"})]),$])]),description:e(()=>[O,o(V,{href:`${c.$page.props.mixpost.docs_link}/books/services-configuration-mixpost/page/facebook`,class:"mt-xs"},null,8,["href"])]),default:e(()=>[o(m,{class:"mt-lg"},{title:e(()=>[l("App ID")]),footer:e(()=>[o(p,{message:s.value.client_id},null,8,["message"])]),default:e(()=>[o(n,{modelValue:t.form.client_id,"onUpdate:modelValue":r[0]||(r[0]=i=>t.form.client_id=i),error:s.value.hasOwnProperty("client_id"),type:"text",class:"w-full",autocomplete:"off"},null,8,["modelValue","error"])]),_:1}),o(m,{class:"mt-lg"},{title:e(()=>[l("App secret")]),footer:e(()=>[o(p,{message:s.value.client_secret},null,8,["message"])]),default:e(()=>[o(n,{modelValue:t.form.client_secret,"onUpdate:modelValue":r[1]||(r[1]=i=>t.form.client_secret=i),error:s.value.hasOwnProperty("client_secret"),type:"password",class:"w-full",autocomplete:"new-password"},null,8,["modelValue","error"])]),_:1}),o(w,{onClick:d,class:"mt-lg"},{default:e(()=>[l("Save")]),_:1})]),_:1}))}};export{j as default};