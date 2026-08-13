import React from 'react';
import { usePage, useForm, router } from '@inertiajs/react';

export default function ApiTokens() {
    const { tokens, flash } = usePage().props;
    const { data, setData, post, processing } = useForm({
        name: ''
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('settings.api-tokens.store'));
    };

    const deleteToken = (id) => {
        router.delete(route('settings.api-tokens.destroy', id));
    };

    return (
        <div className="p-6">
            <h1 className="text-2xl mb-4">API Tokens</h1>
            
            {flash?.token && (
                <div className="bg-green-100 p-4 mb-4">
                    Please copy your new API token, it won't be shown again: <strong>{flash.token}</strong>
                </div>
            )}
            
            <div className="mb-6">
                <form onSubmit={submit} className="flex gap-4">
                    <input 
                        type="text" 
                        value={data.name} 
                        onChange={e => setData('name', e.target.value)} 
                        placeholder="Token Name" 
                        className="border p-2"
                    />
                    <button type="submit" disabled={processing} className="bg-blue-500 text-white p-2">
                        Create Token
                    </button>
                </form>
            </div>
            
            <div>
                <ul>
                    {tokens && tokens.map(t => (
                        <li key={t.id} className="border-b py-2 flex justify-between">
                            <span>{t.name}</span>
                            <button onClick={() => deleteToken(t.id)} className="text-red-500">Revoke</button>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
