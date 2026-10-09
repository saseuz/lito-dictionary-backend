import { Form, Head, Link } from '@inertiajs/react';
import { BookOpen, Search } from 'lucide-react';
import { ManagementPage } from '@/components/admin/management';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { lookup } from '@/routes/admin/dictionary';
import type { DictionaryEntry, DictionaryRelation } from '@/types/dictionary';

function Relations({ items }: { items: DictionaryRelation[] }) {
    if (items.length === 0) return null;
    return (
        <ul className="flex flex-wrap gap-x-5 gap-y-2 text-sm">
            {items.map((item, index) => (
                <li key={index}>
                    <span className="mr-2 text-muted-foreground">
                        {item.kind.replaceAll('_', ' ')}:
                    </span>
                    {item.resolved ? (
                        <Link
                            className="underline underline-offset-4"
                            href={lookup({
                                query: { word: item.text, language: 'en' },
                            })}
                        >
                            {item.text}
                        </Link>
                    ) : (
                        <span>{item.text}</span>
                    )}
                </li>
            ))}
        </ul>
    );
}

export default function DictionaryLookup({
    query,
    available,
    entry,
    error,
}: {
    query: string;
    available: boolean;
    entry: DictionaryEntry | null;
    error: string | null;
}) {
    return (
        <>
            <Head
                title={entry ? `${entry.headword} · Dictionary` : 'Dictionary'}
            />
            <ManagementPage
                title="Dictionary"
                description="Read-only English word lookup. Spelling and capitalization must match the imported entry."
            >
                <Form {...lookup.form()} className="max-w-2xl space-y-3">
                    {({ errors, processing }) => (
                        <>
                            <Label htmlFor="word">English word</Label>
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Input
                                    key={query}
                                    id="word"
                                    name="word"
                                    defaultValue={query}
                                    placeholder="For example, run"
                                    required
                                    maxLength={255}
                                    aria-invalid={!!errors.word}
                                    aria-describedby="word-error"
                                />
                                <input
                                    type="hidden"
                                    name="language"
                                    value="en"
                                />
                                <Button type="submit" disabled={processing}>
                                    <Search className="size-4" />
                                    Look up
                                </Button>
                            </div>
                            <InputError
                                id="word-error"
                                message={errors.word ?? errors.language}
                            />
                        </>
                    )}
                </Form>
                {!entry && (
                    <div
                        className="rounded-xl border border-dashed p-8 text-center sm:p-12"
                        role="status"
                    >
                        <BookOpen className="mx-auto mb-4 size-8 text-muted-foreground" />
                        <h2 className="font-semibold">
                            {error
                                ? error
                                : !available
                                ? 'No dictionary data is active yet'
                                : query
                                  ? `No entry found for “${query}”`
                                  : 'Explore a word'}
                        </h2>
                        <p className="mx-auto mt-2 max-w-lg text-sm text-muted-foreground">
                            {error
                                ? 'The lookup provider could not return a response. Check the provider configuration or try again.'
                                : !available
                                ? 'An operator must import and activate a reviewed dataset before entries appear here.'
                                : query
                                  ? 'Try the exact spelling and capitalization. The current fixture contains only a few words.'
                                  : 'Enter an English word to see its meanings, pronunciation, forms, and source credit.'}
                        </p>
                    </div>
                )}
                {entry && (
                    <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                        <div className="min-w-0 space-y-6">
                            <header className="border-b pb-5">
                                <p className="mb-2 text-sm text-muted-foreground">
                                    {entry.language}
                                </p>
                                <h2 className="text-4xl font-semibold tracking-tight break-words">
                                    {entry.headword}
                                </h2>
                            </header>
                            {entry.lexemes.map((lexeme) => (
                                <article
                                    key={lexeme.id}
                                    className="space-y-5 rounded-xl border p-5 sm:p-6"
                                >
                                    <header className="flex flex-wrap items-center justify-between gap-3">
                                        <h3 className="font-semibold">
                                            {lexeme.partOfSpeech}
                                            {lexeme.etymologyKey
                                                ? ` · Origin ${lexeme.etymologyKey}`
                                                : ''}
                                        </h3>
                                        <a
                                            className="text-sm underline underline-offset-4"
                                            href={lexeme.sourceUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Provider source ↗
                                        </a>
                                    </header>
                                    {lexeme.pronunciations.length > 0 && (
                                        <ul className="flex flex-wrap gap-3 text-sm">
                                            {lexeme.pronunciations.map(
                                                (sound, i) => (
                                                    <li
                                                        key={i}
                                                        className="rounded-md bg-muted px-3 py-1.5"
                                                    >
                                                        <span className="mr-2 text-muted-foreground">
                                                            {sound.form ??
                                                                entry.headword}
                                                        </span>
                                                        {sound.text}
                                                        {sound.tags.length >
                                                            0 && (
                                                            <span className="ml-2 text-muted-foreground">
                                                                {sound.tags.join(
                                                                    ', ',
                                                                )}
                                                            </span>
                                                        )}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                    {lexeme.etymology && (
                                        <p className="text-sm text-muted-foreground">
                                            {lexeme.etymology}
                                        </p>
                                    )}
                                    {lexeme.senses.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            No meanings supplied for this entry.
                                        </p>
                                    )}
                                    <ol className="space-y-6">
                                        {lexeme.senses.map((sense, index) => (
                                            <li
                                                key={sense.id}
                                                className="flex gap-4"
                                            >
                                                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium">
                                                    {index + 1}
                                                </span>
                                                <div className="min-w-0 flex-1 space-y-3">
                                                    {sense.tags.length > 0 && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {sense.tags.join(
                                                                ' · ',
                                                            )}
                                                        </p>
                                                    )}
                                                    {sense.definitions.map(
                                                        (definition, i) => (
                                                            <p
                                                                key={i}
                                                                className="leading-relaxed break-words"
                                                            >
                                                                {definition}
                                                            </p>
                                                        ),
                                                    )}
                                                    {sense.definitions
                                                        .length === 0 && (
                                                        <p className="text-sm text-muted-foreground">
                                                            No definition text
                                                            supplied.
                                                        </p>
                                                    )}
                                                    {sense.examples.map(
                                                        (example, i) => (
                                                            <figure
                                                                key={i}
                                                                className="border-l-2 pl-4"
                                                            >
                                                                <blockquote className="text-sm italic">
                                                                    {
                                                                        example.text
                                                                    }
                                                                </blockquote>
                                                                <figcaption className="mt-1 text-xs text-muted-foreground">
                                                                    {example.reference &&
                                                                        `${example.reference} · `}
                                                                    {
                                                                        example.attribution
                                                                    }{' '}
                                                                    ·{' '}
                                                                    <a
                                                                        className="underline"
                                                                        href={
                                                                            example.licenseUrl
                                                                        }
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                    >
                                                                        {
                                                                            example.license
                                                                        }
                                                                    </a>
                                                                </figcaption>
                                                            </figure>
                                                        ),
                                                    )}
                                                    <Relations
                                                        items={sense.relations}
                                                    />
                                                </div>
                                            </li>
                                        ))}
                                    </ol>
                                    {lexeme.forms.length > 0 && (
                                        <section className="border-t pt-4">
                                            <h4 className="mb-3 text-sm font-medium">
                                                Forms
                                            </h4>
                                            <ul className="flex flex-wrap gap-2">
                                                {lexeme.forms.map((form, i) => (
                                                    <li
                                                        key={i}
                                                        className="rounded-md border px-3 py-2 text-sm"
                                                    >
                                                        <span className="font-medium">
                                                            {form.text}
                                                        </span>
                                                        {form.tags.length >
                                                            0 && (
                                                            <span className="ml-2 text-muted-foreground">
                                                                {form.tags.join(
                                                                    ', ',
                                                                )}
                                                            </span>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        </section>
                                    )}
                                    <Relations items={lexeme.relations} />
                                </article>
                            ))}
                        </div>
                        <aside className="min-w-0 space-y-4 rounded-xl bg-muted/50 p-5 text-sm">
                            <h3 className="font-semibold">
                                Source & attribution
                            </h3>
                            {entry.attribution.artifactKind === 'fixture' && (
                                <p className="rounded-md border bg-background p-3">
                                    Small demonstration fixture · incomplete
                                    coverage
                                </p>
                            )}
                            <p>{entry.attribution.text}</p>
                            <p>
                                <a
                                    className="underline underline-offset-4"
                                    href={entry.attribution.licenseUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {entry.attribution.license}
                                </a>{' '}
                                ·{' '}
                                <a
                                    className="underline underline-offset-4"
                                    href={entry.attribution.sourceUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Dictionary provider
                                </a>
                            </p>
                            <p className="text-muted-foreground">
                                {entry.attribution.transformations}
                            </p>
                            <details className="border-t pt-3">
                                <summary className="cursor-pointer font-medium">
                                    Release details
                                </summary>
                                <dl className="mt-3 space-y-3 text-xs">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Release
                                        </dt>
                                        <dd className="mt-1 font-mono break-all">
                                            {entry.attribution.releaseKey}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Upstream dump
                                        </dt>
                                        <dd>
                                            {entry.attribution.dumpDate ??
                                                'Not applicable to this handcrafted fixture'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Extractor
                                        </dt>
                                        <dd>
                                            {entry.attribution.extractorVersion}
                                        </dd>
                                    </div>
                                </dl>
                            </details>
                        </aside>
                    </div>
                )}
            </ManagementPage>
        </>
    );
}
DictionaryLookup.layout = {
    breadcrumbs: [{ title: 'Dictionary', href: lookup() }],
};
