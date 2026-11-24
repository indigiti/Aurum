'use client';
import Workspace from '../presentation/Workspace';
import {browserApi,quoteStream} from '../infrastructure/browser-client';
export default function Home(){return <Workspace client={browserApi} streamSource={quoteStream}/>;}
