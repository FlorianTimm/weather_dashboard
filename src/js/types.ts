export type Numberish = number | string;

export interface LiveApiResponse {
    current: CurrentWeather;
    calculated: CalculatedWeather;
    records: WeatherRecords;
    daily_stats?: Record<string, Numberish | string | null> | null;
}

export interface CurrentWeather {
    zeitstempel: string;
    intem: Numberish;
    inhum: Numberish;
    t1tem: Numberish;
    t1hum: Numberish;
    t1wdir: Numberish;
    t1raindy: Numberish;
    t1rainra: Numberish;
    t1ws: Numberish;
    t1wgust: Numberish;
    t1ws10mav: Numberish;
    t1solrad: Numberish;
}

export interface CalculatedWeather {
    wind_speed_kmh: Numberish;
    solar_theo: Numberish;
    cloudiness: Numberish;
    af_in: Numberish;
    af_out: Numberish;
    laerm_index: Numberish;
    traffic_noise: Numberish;
    traffic_flow: Numberish;
    schall_leitung: Numberish;
}

export interface WeatherRecord {
    val: Numberish;
    date: string;
    time?: string | null;
}

export interface WeatherRecords {
    max_temp: WeatherRecord;
    min_temp: WeatherRecord;
    max_wind: WeatherRecord;
    max_rain: WeatherRecord;
}

export type RangeId = "24h" | "7d" | "30d";
export type AxisId = "temperature" | "wind" | "rain" | "percent" | "solar" | "pressure";
export type SeriesId =
    | "temp"
    | "temp_in"
    | "rbar"
    | "wind"
    | "rain_rate"
    | "traffic_flow"
    | "traffic_noise"
    | "dew_in"
    | "dew_out"
    | "hum_in"
    | "solar_theo"
    | "solar_meas"
    | "cloudiness";

export type ChartDataKey =
    | SeriesId
    | "wind_dir"
    | "temp_min"
    | "temp_max"
    | "temp_in_min"
    | "temp_in_max"
    | "dew_out_min"
    | "dew_out_max"
    | "hum_out_min"
    | "hum_out_max"
    | "hum_in_min"
    | "hum_in_max"
    | "rbar_min"
    | "rbar_max"
    | "solar_meas_min"
    | "solar_meas_max";

export interface ChartPayload {
    labels: string[];
    timestamps: number[];

    temp: Array<number | null>;
    temp_min: Array<number | null>;
    temp_max: Array<number | null>;

    temp_in: Array<number | null>;
    temp_in_min: Array<number | null>;
    temp_in_max: Array<number | null>;

    dew_in: Array<number | null>;
    dew_out: Array<number | null>;
    dew_out_min: Array<number | null>;
    dew_out_max: Array<number | null>;

    wind: Array<number | null>;
    wind_dir: Array<number | null>;
    rain_rate: Array<number | null>;

    hum_out: Array<number | null>;
    hum_out_min: Array<number | null>;
    hum_out_max: Array<number | null>;
    hum_in: Array<number | null>;
    hum_in_min: Array<number | null>;
    hum_in_max: Array<number | null>;

    rbar: Array<number | null>;
    rbar_min: Array<number | null>;
    rbar_max: Array<number | null>;

    solar_meas: Array<number | null>;
    solar_meas_min: Array<number | null>;
    solar_meas_max: Array<number | null>;
    solar_theo: Array<number | null>;
    cloudiness: Array<number | null>;

    traffic_flow: Array<number | null>;
    traffic_noise: Array<number | null>;
}
