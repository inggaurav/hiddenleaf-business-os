import React from 'react';
import { Head } from '@inertiajs/react';

export default function PrintReport({reportType,data}:{reportType:string;data:any}) {
  const rows = data?.rows ?? data?.transactions ?? [];
  return <div className="min-h-screen bg-white text-black p-8 print:p-0">
    <Head title={`Print — ${reportType}`} />
    <div className="max-w-5xl mx-auto">
      <div className="flex justify-between items-start border-b pb-4 mb-6"><div><h1 className="text-2xl font-bold capitalize">HiddenLeaf BusinessOS</h1><p className="text-sm capitalize">{reportType.replaceAll('-',' ')}</p></div><button onClick={()=>window.print()} className="print:hidden border rounded px-3 py-1 text-sm">Print</button></div>
      {Array.isArray(rows) && rows.length>0 ? <table className="w-full text-xs border-collapse"><thead><tr>{Object.keys(rows[0]).map(k=><th key={k} className="border p-2 text-left capitalize">{k.replaceAll('_',' ')}</th>)}</tr></thead><tbody>{rows.map((row:any,index:number)=><tr key={index}>{Object.keys(rows[0]).map(k=><td key={k} className="border p-2">{typeof row[k]==='object'?JSON.stringify(row[k]):String(row[k]??'')}</td>)}</tr>)}</tbody></table> : <pre className="text-xs whitespace-pre-wrap">{JSON.stringify(data,null,2)}</pre>}
    </div>
  </div>;
}
