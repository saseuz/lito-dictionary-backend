export type DictionaryRelation = {
    kind: string;
    text: string;
    resolved: boolean;
};
export type DictionaryEntry = {
    headword: string;
    language: string;
    lexemes: {
        id: number;
        partOfSpeech: string;
        etymology: string | null;
        etymologyKey: string | null;
        sourceUrl: string;
        pronunciations: {
            notation: string;
            text: string;
            form: string | null;
            tags: string[];
        }[];
        forms: { text: string; tags: string[] }[];
        relations: DictionaryRelation[];
        senses: {
            id: number;
            definitions: string[];
            tags: string[];
            examples: {
                text: string;
                reference: string | null;
                attribution: string;
                license: string;
                licenseUrl: string;
            }[];
            relations: DictionaryRelation[];
        }[];
    }[];
    attribution: {
        text: string;
        license: string;
        licenseUrl: string;
        sourceUrl: string;
        releaseKey: string;
        artifactKind: string;
        dumpDate: string | null;
        extractorVersion: string;
        transformations: string;
    };
};
