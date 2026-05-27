import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';

import Dashboard from './react-admin/Dashboard.jsx';
import Settings from './react-admin/Settings.jsx';
import PremiumFeatures from './react-admin/PremiumFeatures.jsx';

let render_comp = null;
let container = null;

try{
    //Get Page query
    const search = window.location.search;
    const params = new URLSearchParams(search);
    const page = params.get('page');
    /***********************************
     * Switch For Pages *
     ***********************************
    */
    switch (page) {
        case 'marginminds-gst':
            container = document.getElementById('marginminds-dashboard');
            render_comp = <Dashboard {...JSON.parse(container?.dataset.dashboard || '{}')} />;
            break;
        case 'marginminds-gst-features':
            container = document.getElementById('marginminds-premium');
            render_comp = <PremiumFeatures upgradeUrl={container?.dataset.upgradeUrl || ''} />;
            break;
        case 'marginminds-gst-settings':
            container = document.getElementById('marginminds-settings');
            const saveAction = container?.dataset.saveAction || '';
            const initialSettings = JSON.parse(container?.dataset.settings || '{}');
            render_comp = <Settings saveAction={saveAction} initialSettings={initialSettings} />;
            break;
        default:
            console.log('No matching page found');
    }
} catch (exception) {
  console.log(exception);
}

if (container && render_comp) {
    const root = createRoot(container);
    root.render(render_comp);
}