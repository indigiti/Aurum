import type {Metadata} from 'next';
import './style.css';
export const metadata:Metadata={title:'Aurum | Gold trading simulator',description:'XAUUSD simulated prop trading terminal'};
export default function Layout({children}:{children:React.ReactNode}){return <html lang="en"><body>{children}</body></html>}
