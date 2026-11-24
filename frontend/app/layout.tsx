import type {Metadata} from 'next';
import '@fontsource-variable/inter';
import '@fontsource-variable/manrope';
import '@fontsource/ibm-plex-mono/400.css';
import '@fontsource/ibm-plex-mono/500.css';
import './style.css';
import './workspace-v3.css';
export const metadata:Metadata={title:'Aurum | Gold trading simulator',description:'XAUUSD simulated prop trading terminal',manifest:(process.env.AURUM_BASE_PATH||'')+'/manifest.webmanifest',icons:{icon:(process.env.AURUM_BASE_PATH||'')+'/aurum-icon.svg'}};
export default function Layout({children}:{children:React.ReactNode}){return <html lang="en"><body>{children}</body></html>}
