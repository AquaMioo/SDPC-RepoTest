import { Head, router } from '@inertiajs/react';
import { MagnifyingGlassIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Input, Select } from '@/components/sdpc/input';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { index as transactionsIndex } from '@/routes/transactions';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const TAG_VARIANT: Record<string, 'accent' | 'neutral' | 'outline'> = {
    accent: 'accent',
    neutral: 'neutral',
    outline: 'outline',
};

const peso = new Intl.NumberFormat('en-PH');

type Option = { value: string; label: string };

type Props = {
    transactions: {
        id: number;
        reference: string;
        date: string | null;
        description: string | null;
        wallet: string;
        benefitPeriod: string | null;
        type: string;
        amount: number;
        status: string;
        statusLabel: string;
        statusVariant: string;
    }[];
    totals: { settled: number; outstanding: number };
    filters: { wallets: Option[]; types: Option[]; statuses: Option[] };
    isStudent: boolean;
};

/**
 * "My transactions" for a student, the billing history for a client.
 *
 * Reachable only when config('billing.enabled') is true, which it is not on a
 * normal boot: EnsureBillingIsEnabled 404s the route and the nav item stays
 * disabled. The screen is built so switching the ledger on is a configuration
 * change rather than a build.
 */
export default function Transactions({
    transactions,
    totals,
    filters,
    isStudent,
}: Props) {
    const team = useCurrentTeam();

    const [search, setSearch] = useState('');
    const [wallet, setWallet] = useState('');
    const [type, setType] = useState('');
    const [sort, setSort] = useState('date');

    const apply = (overrides: Record<string, string> = {}) => {
        router.get(
            transactionsIndex.url(team.slug),
            { search, wallet, type, sort, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Transactions" />

            <div
                style={{
                    maxWidth: 1160,
                    margin: '0 auto',
                    padding: '30px clamp(16px, 4vw, 32px) 72px',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 16,
                        marginBottom: 22,
                    }}
                >
                    <h3 style={{ margin: 0, marginRight: 'auto' }}>
                        {isStudent ? 'My transactions' : 'Billing history'}
                    </h3>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(200px, 1fr))',
                        gap: 16,
                        marginBottom: 18,
                    }}
                >
                    <Panel style={{ padding: 16, gap: 4 }}>
                        <PanelKicker>
                            {isStudent ? 'Earned' : 'Settled'}
                        </PanelKicker>
                        <span
                            style={{
                                fontFamily: 'var(--font-heading)',
                                fontSize: 26,
                                fontVariantNumeric: 'tabular-nums',
                            }}
                        >
                            ₱ {peso.format(totals.settled)}
                        </span>
                    </Panel>
                    <Panel style={{ padding: 16, gap: 4 }}>
                        <PanelKicker>Outstanding</PanelKicker>
                        <span
                            style={{
                                fontFamily: 'var(--font-heading)',
                                fontSize: 26,
                                fontVariantNumeric: 'tabular-nums',
                                color: MUTED(70),
                            }}
                        >
                            ₱ {peso.format(totals.outstanding)}
                        </span>
                    </Panel>
                </div>

                <Panel style={{ padding: 16, gap: 12, marginBottom: 18 }}>
                    <form
                        style={{ display: 'flex', gap: 10 }}
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply();
                        }}
                    >
                        <div style={{ position: 'relative', flex: 1 }}>
                            <MagnifyingGlassIcon
                                style={{
                                    position: 'absolute',
                                    left: 11,
                                    top: 10,
                                    fontSize: 15,
                                    color: MUTED(68),
                                }}
                            />
                            <Input
                                style={{ paddingLeft: 33 }}
                                value={search}
                                aria-label="Search transactions"
                                placeholder="Search by description, wallet, type, or amount"
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>
                        <Btn
                            variant="primary"
                            type="submit"
                            style={{ paddingInline: 20 }}
                        >
                            Search
                        </Btn>
                    </form>

                    <div style={{ display: 'flex', gap: 10 }}>
                        <Select
                            aria-label="Sort by"
                            style={{ width: 'auto', paddingRight: 26 }}
                            value={sort}
                            onChange={(event) => {
                                setSort(event.target.value);
                                apply({ sort: event.target.value });
                            }}
                        >
                            <option value="date">Sort by: Date</option>
                            <option value="amount">Sort by: Amount</option>
                        </Select>

                        <Select
                            aria-label="Wallet"
                            style={{ width: 'auto', paddingRight: 26 }}
                            value={wallet}
                            onChange={(event) => {
                                setWallet(event.target.value);
                                apply({ wallet: event.target.value });
                            }}
                        >
                            <option value="">Wallet: All</option>
                            {filters.wallets.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>

                        <Select
                            aria-label="Type"
                            style={{ width: 'auto', paddingRight: 26 }}
                            value={type}
                            onChange={(event) => {
                                setType(event.target.value);
                                apply({ type: event.target.value });
                            }}
                        >
                            <option value="">Type: All</option>
                            {filters.types.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                </Panel>

                <Panel style={{ padding: '14px 6px' }}>
                    {transactions.length === 0 ? (
                        <div
                            style={{
                                padding: '18px 16px',
                                fontSize: 12.5,
                                color: MUTED(68),
                            }}
                        >
                            No transactions yet. A row is written when a client
                            approves a milestone.
                        </div>
                    ) : (
                        <div className="table-wrap">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th style={{ paddingLeft: 16 }}>
                                            Date
                                        </th>
                                        <th>Description</th>
                                        <th>Wallet</th>
                                        <th>Benefit period</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th
                                            style={{
                                                textAlign: 'right',
                                                paddingRight: 16,
                                            }}
                                        >
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transactions.map((transaction) => (
                                        <tr key={transaction.id}>
                                            <td
                                                style={{
                                                    paddingLeft: 16,
                                                    whiteSpace: 'nowrap',
                                                    color: MUTED(65),
                                                }}
                                            >
                                                {transaction.date}
                                            </td>
                                            <td>{transaction.description}</td>
                                            <td style={{ color: MUTED(65) }}>
                                                {transaction.wallet}
                                            </td>
                                            <td style={{ color: MUTED(65) }}>
                                                {transaction.benefitPeriod ??
                                                    '—'}
                                            </td>
                                            <td>
                                                <Tag variant="neutral">
                                                    {transaction.type}
                                                </Tag>
                                            </td>
                                            <td>
                                                <Tag
                                                    variant={
                                                        TAG_VARIANT[
                                                            transaction
                                                                .statusVariant
                                                        ] ?? 'neutral'
                                                    }
                                                >
                                                    {transaction.statusLabel}
                                                </Tag>
                                            </td>
                                            <td
                                                style={{
                                                    textAlign: 'right',
                                                    paddingRight: 16,
                                                    fontVariantNumeric:
                                                        'tabular-nums',
                                                }}
                                            >
                                                ₱{' '}
                                                {peso.format(
                                                    transaction.amount,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            padding: '14px 16px 4px',
                        }}
                    >
                        <span
                            style={{
                                fontSize: 12,
                                color: MUTED(68),
                                marginRight: 'auto',
                            }}
                        >
                            Showing {transactions.length} transaction
                            {transactions.length === 1 ? '' : 's'}
                        </span>
                    </div>
                </Panel>
            </div>
        </>
    );
}
