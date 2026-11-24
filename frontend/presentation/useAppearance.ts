'use client';
import {useEffect,useState} from 'react';
export type Theme='dark'|'light';
export function useAppearance(){
 const [theme,setTheme]=useState<Theme>('dark');
 const [collapsed,setCollapsed]=useState(false);
 const [ticketWidth,setTicketWidth]=useState(310);
 useEffect(()=>{try{const v=localStorage.getItem('aurum-theme');if(v==='light'||v==='dark')setTheme(v);setCollapsed(localStorage.getItem('aurum-nav')==='compact');const w=Number(localStorage.getItem('aurum-ticket-width'));if(w>=260&&w<=420)setTicketWidth(w)}catch{}},[]);
 function toggleTheme(){const next=theme==='dark'?'light':'dark';setTheme(next);try{localStorage.setItem('aurum-theme',next)}catch{}}
 function toggleSidebar(){const next=!collapsed;setCollapsed(next);try{localStorage.setItem('aurum-nav',next?'compact':'expanded')}catch{}}
 function resizeTicket(width:number){setTicketWidth(width);try{localStorage.setItem('aurum-ticket-width',String(width))}catch{}}
 useEffect(()=>{document.documentElement.dataset.theme=theme;return()=>{delete document.documentElement.dataset.theme}},[theme]);
 return {theme,toggleTheme,collapsed,toggleSidebar,ticketWidth,resizeTicket};
}
