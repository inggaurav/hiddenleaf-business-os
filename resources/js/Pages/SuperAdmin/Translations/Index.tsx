import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';

export default function TranslationsIndex({ translations, locales }: any) {
    const [selectedLocale, setSelectedLocale] = useState('en');
    
    const { data, setData, post, processing, reset } = useForm({
        locale: 'en',
        key: '',
        value: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/super-admin/translations', {
            onSuccess: () => reset('key', 'value')
        });
    };

    return (
        <div className="min-h-screen bg-gray-100">
            <Head title="Translations" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h1 className="text-2xl font-bold mb-6">Translations</h1>
                        
                        <div className="mb-4">
                            <label className="mr-4">Select Locale:</label>
                            <select 
                                value={selectedLocale} 
                                onChange={(e) => {
                                    setSelectedLocale(e.target.value);
                                    setData('locale', e.target.value);
                                }}
                                className="rounded border-gray-300"
                            >
                                {locales.map((loc: string) => (
                                    <option key={loc} value={loc}>{loc.toUpperCase()}</option>
                                ))}
                            </select>
                        </div>

                        <form onSubmit={submit} className="mb-8 p-4 bg-gray-50 rounded border flex gap-4 items-end">
                            <div className="flex-1">
                                <label className="block text-sm">Translation Key</label>
                                <input type="text" value={data.key} onChange={e => setData('key', e.target.value)} required className="mt-1 block w-full rounded border-gray-300"/>
                            </div>
                            <div className="flex-1">
                                <label className="block text-sm">Translation Value</label>
                                <input type="text" value={data.value} onChange={e => setData('value', e.target.value)} required className="mt-1 block w-full rounded border-gray-300"/>
                            </div>
                            <button type="submit" disabled={processing} className="bg-blue-600 text-white px-4 py-2 rounded">
                                Add / Update
                            </button>
                        </form>

                        <div className="mt-4">
                            <h2 className="text-xl mb-4">Current Translations ({selectedLocale})</h2>
                            <table className="min-w-full bg-white border">
                                <thead>
                                    <tr>
                                        <th className="py-2 border-b">Key</th>
                                        <th className="py-2 border-b">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {Object.entries(translations[selectedLocale] || {}).map(([k, v]) => (
                                        <tr key={k}>
                                            <td className="py-2 px-4 border-b">{k}</td>
                                            <td className="py-2 px-4 border-b">{String(v)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
