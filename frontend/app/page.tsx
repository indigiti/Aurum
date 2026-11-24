'use client';
import {useEffect} from 'react';
import {registerAurumApp} from '../infrastructure/installApp';
import Workspace from '../presentation/Workspace';
import {browserApi,quoteStream} from '../infrastructure/browser-client';
export default function Home(){useEffect(()=>{registerAurumApp()},[]);return <Workspace client={browserApi} streamSource={quoteStream}/>;}
