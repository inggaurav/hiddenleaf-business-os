import React from 'react';
import { usePage, useForm } from '@inertiajs/react';

export default function EmailTemplates() {
    const { templates } = usePage().props;
    const { data, setData, post, processing } = useForm({
        name: '',
        subject: '',
        body: ''
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('settings.email-templates.store'));
    };

    return (
        <div className="p-6">
            <h1 className="text-2xl mb-4">Email Templates</h1>
            <div className="mb-6">
                <form onSubmit={submit} className="flex flex-col gap-4 max-w-md">
                    <input 
                        type="text" 
                        value={data.name} 
                        onChange={e => setData('name', e.target.value)} 
                        placeholder="Name" 
                        className="border p-2"
                    />
                    <input 
                        type="text" 
                        value={data.subject} 
                        onChange={e => setData('subject', e.target.value)} 
                        placeholder="Subject" 
                        className="border p-2"
                    />
                    <textarea 
                        value={data.body} 
                        onChange={e => setData('body', e.target.value)} 
                        placeholder="Body" 
                        className="border p-2"
                    />
                    <button type="submit" disabled={processing} className="bg-blue-500 text-white p-2">
                        Create
                    </button>
                </form>
            </div>
            <div>
                <ul>
                    {templates && templates.map(t => (
                        <li key={t.id} className="border-b py-2">{t.name} - {t.subject}</li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
