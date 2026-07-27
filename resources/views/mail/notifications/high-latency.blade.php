# High Search Latency Alert

A search query exceeded the configured latency threshold.

| Detail | Value |
|--------|-------|
| **Query** | `{{ $query }}` |
| **Latency** | {{ $latencyMs }} ms |
| **Providers queried** | {{ $providersQueried }} |
| **Timestamp** | {{ $timestamp }} |

Please investigate potential performance bottlenecks in the knowledge retrieval pipeline.
