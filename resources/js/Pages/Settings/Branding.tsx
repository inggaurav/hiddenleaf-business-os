import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Building2, Palette, Image as ImageIcon, Save, ArrowLeft } from 'lucide-react';

interface BrandingProps {
  organization: {
    id: number;
    name: string;
    brand_name: string | null;
    brand_logo_path: string | null;
    brand_primary_color: string | null;
    brand_footer_text: string | null;
  };
  isOrgOwner: boolean;
}

export default function Branding({ organization, isOrgOwner }: BrandingProps) {
  const { flash } = usePage<any>().props;

  const [brandName, setBrandName] = useState(organization.brand_name || '');
  const [brandFooterText, setBrandFooterText] = useState(organization.brand_footer_text || '');
  const [brandPrimaryColor, setBrandPrimaryColor] = useState(organization.brand_primary_color || '#8B5CF6');
  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [logoPreview, setLogoPreview] = useState<string | null>(organization.brand_logo_path || null);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setLogoFile(file);
      setLogoPreview(URL.createObjectURL(file));
    }
  };

  const handleColorChange = (hex: string) => {
    setBrandPrimaryColor(hex);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setErrors({});

    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('brand_name', brandName);
    formData.append('brand_footer_text', brandFooterText);
    formData.append('brand_primary_color', brandPrimaryColor);
    if (logoFile) {
      formData.append('brand_logo', logoFile);
    }

    router.post('/organization/branding', formData, {
      preserveScroll: true,
      onSuccess: () => {
        setSubmitting(false);
      },
      onError: (err) => {
        setSubmitting(false);
        setErrors(err);
      },
    });
  };

  return (
    <AppShell
      title="Branding Settings"
      breadcrumbs={[
        { label: 'Settings', href: '/settings' },
        { label: 'Organization Branding' },
      ]}
    >
      <Head title="Organization Branding â€” HiddenLeaf" />

      <div className="max-w-4xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold text-[var(--text-primary)]">Organization White-Label Branding</h1>
            <p className="text-xs text-[var(--text-tertiary)] mt-1">
              Customize company identity, custom logos, and primary accent color for your workspace members.
            </p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Company Identity */}
          <Card level={0} padded={false}>
            <CardHeader
              title="Company Identity"
              subtitle="Name and footer branding displayed across the sidebar and reports."
              actions={<Building2 className="w-4 h-4 text-[var(--text-tertiary)]" />}
            />
            <CardBody className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">
                    Brand Name (Display Name)
                  </label>
                  <Input
                    placeholder={organization.name}
                    value={brandName}
                    onChange={(e) => setBrandName(e.target.value)}
                    error={errors.brand_name}
                    maxLength={120}
                  />
                  <p className="text-[11px] text-[var(--text-tertiary)] mt-1">
                    Overrides default platform name in the top-left sidebar header.
                  </p>
                </div>

                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">
                    Brand Subtitle / Footer Text
                  </label>
                  <Input
                    placeholder="e.g. Enterprise Edition / Acme Corp"
                    value={brandFooterText}
                    onChange={(e) => setBrandFooterText(e.target.value)}
                    error={errors.brand_footer_text}
                    maxLength={255}
                  />
                  <p className="text-[11px] text-[var(--text-tertiary)] mt-1">
                    Small caption text displayed under the brand title.
                  </p>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* Logo Upload */}
          <Card level={0} padded={false}>
            <CardHeader
              title="Organization Logo"
              subtitle="Upload your organization's logo (PNG, JPG, SVG, WebP up to 2MB)."
              actions={<ImageIcon className="w-4 h-4 text-[var(--text-tertiary)]" />}
            />
            <CardBody className="space-y-4">
              <div className="flex flex-col sm:flex-row items-start sm:items-center gap-6">
                <div className="w-24 h-24 rounded-2xl bg-[var(--surface-2)] border border-[var(--border-subtle)] flex items-center justify-center p-2 overflow-hidden shrink-0">
                  {logoPreview ? (
                    <img src={logoPreview} alt="Logo preview" className="max-w-full max-h-full object-contain" />
                  ) : (
                    <span className="text-xs text-[var(--text-tertiary)] text-center font-medium">No Logo</span>
                  )}
                </div>

                <div className="space-y-2 flex-1">
                  <input
                    type="file"
                    id="brand-logo-file"
                    accept="image/*"
                    onChange={handleLogoChange}
                    className="block w-full text-xs text-[var(--text-secondary)] file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[var(--surface-3)] file:text-[var(--text-primary)] hover:file:bg-[var(--border-medium)] cursor-pointer"
                  />
                  {errors.brand_logo && (
                    <p className="text-xs text-rose-400">{errors.brand_logo}</p>
                  )}
                  <p className="text-[11px] text-[var(--text-tertiary)]">
                    Recommended dimensions: 240x60px with transparent background.
                  </p>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* Primary Accent Color */}
          <Card level={0} padded={false}>
            <CardHeader
              title="Primary Accent Color"
              subtitle="Customize the primary theme color for interactive buttons, tabs, and accents."
              actions={<Palette className="w-4 h-4 text-[var(--text-tertiary)]" />}
            />
            <CardBody className="space-y-4">
              <div className="flex flex-wrap items-center gap-4">
                {/* Color picker native */}
                <div className="relative">
                  <input
                    type="color"
                    id="brand_color_native"
                    value={brandPrimaryColor.startsWith('#') ? brandPrimaryColor : '#8B5CF6'}
                    onChange={(e) => handleColorChange(e.target.value)}
                    className="w-12 h-12 rounded-xl border border-[var(--border-subtle)] bg-transparent cursor-pointer p-0.5"
                  />
                </div>

                {/* Hex input */}
                <div className="w-36">
                  <Input
                    placeholder="#8B5CF6"
                    value={brandPrimaryColor}
                    onChange={(e) => handleColorChange(e.target.value)}
                    error={errors.brand_primary_color}
                    maxLength={7}
                  />
                </div>

                {/* Live Preview Swatch */}
                <div className="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-2)]">
                  <div
                    className="w-4 h-4 rounded-full border border-white/20 shadow-sm"
                    style={{ backgroundColor: brandPrimaryColor }}
                  />
                  <span className="text-xs font-mono text-[var(--text-secondary)]">
                    {brandPrimaryColor}
                  </span>
                </div>
              </div>

              {errors.brand_primary_color && (
                <p className="text-xs text-rose-400">{errors.brand_primary_color}</p>
              )}
            </CardBody>
          </Card>

          {/* Submit Button */}
          <div className="flex justify-end gap-3 pt-2">
            <Button
              type="submit"
              variant="neutral"
              loading={submitting}
              icon={<Save className="w-4 h-4" />}
            >
              Save Branding
            </Button>
          </div>
        </form>
      </div>
    </AppShell>
  );
}
