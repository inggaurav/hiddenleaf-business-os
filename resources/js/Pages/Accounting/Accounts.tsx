import ModuleDashboard from '@/Components/ModuleDashboard';

export default function Accounts() {
  return <ModuleDashboard title="Chart of Accounts" description="Live workspace ledger, bank-account, journal, and transfer activity." metricLabels={{ accounts: 'Ledger Accounts', bank_accounts: 'Bank Accounts', posted_journals: 'Posted Journals', bank_transfers: 'Bank Transfers' }} collections={[{ key: 'accounts', title: 'Ledger Accounts', columns: ['code', 'name', 'type', 'currency', 'is_bank', 'is_active'] }, { key: 'types', title: 'Account Types', columns: ['name', 'classification', 'normal_balance'] }]} />;
}
