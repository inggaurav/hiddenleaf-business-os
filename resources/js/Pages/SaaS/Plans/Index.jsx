import React from 'react';
export default function Index({ plans }) { return (<div><h1>Plans</h1><ul>{plans.map(p => <li key={p.id}>{p.name} - /mo</li>)}</ul></div>); }
