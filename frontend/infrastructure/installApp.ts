export function registerAurumApp(){if(typeof window!=='undefined'&&window.isSecureContext&&'serviceWorker' in navigator)void navigator.serviceWorker.register('./sw.js',{scope:'./'}).catch(()=>{});}
