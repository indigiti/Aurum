import type {ButtonHTMLAttributes,ReactNode} from 'react';
export default function HeaderIcon({label,children,className='',...props}:ButtonHTMLAttributes<HTMLButtonElement>&{label:string;children:ReactNode}){
 return <button type="button" {...props} aria-label={label} title={label} data-tooltip={label} className={'headerIcon '+className}>{children}</button>
}
