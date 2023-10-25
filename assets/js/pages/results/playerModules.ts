export interface PlayerModuleInterface {
    load: (body: HTMLDivElement, id: number) => Promise<void>;
    init: (wrapper: HTMLDivElement) => Promise<void>;
}

export interface DistributionModuleInterface {
    selectedPlayer: number;
    selectedParam: string;
    setTitle: (title: string) => void;
    load: () => Promise<void>;
    show: () => void;
    hide: () => void;
}

export interface LeaderboardModuleInterface {
    load: (url: string) => Promise<void>;
    show: () => void;
    hide: () => void;
}

export interface EloModuleInterface {
    load: (url: string) => Promise<void>;
    show: () => void;
    hide: () => void;
}