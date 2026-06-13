import React from "react";
import * as Tabs from "@radix-ui/react-tabs";
import WooFilterParams from "../components/WooFilterParams";
import TaxonomyNoindexGrid from "../components/TaxonomyNoindexGrid";
import AttributeMapping from "./AttributeMapping";

export default function WooCommerceSEO() {
  return (
    <div>
      <h2 className="text-2xl font-semibold text-gray-900">WooCommerce SEO</h2>
      <p className="mt-1 mb-6 text-sm text-gray-500">
        Configure SEO settings for WooCommerce shop pages, faceted URLs,
        taxonomy archives, and product schema attributes.
      </p>

      <Tabs.Root defaultValue="faceted-urls">
        <Tabs.List className="mb-4 flex flex-wrap gap-1 border-b border-gray-200 pb-2">
          <Tabs.Trigger
            value="faceted-urls"
            className="rounded-md px-3 py-1.5 text-sm text-gray-600 transition-colors data-[state=active]:bg-white data-[state=active]:font-medium data-[state=active]:text-gray-900 data-[state=active]:shadow-sm"
          >
            Faceted URL Protection
          </Tabs.Trigger>
          <Tabs.Trigger
            value="taxonomy-controls"
            className="rounded-md px-3 py-1.5 text-sm text-gray-600 transition-colors data-[state=active]:bg-white data-[state=active]:font-medium data-[state=active]:text-gray-900 data-[state=active]:shadow-sm"
          >
            Taxonomy Controls
          </Tabs.Trigger>
          <Tabs.Trigger
            value="attribute-mapping"
            className="rounded-md px-3 py-1.5 text-sm text-gray-600 transition-colors data-[state=active]:bg-white data-[state=active]:font-medium data-[state=active]:text-gray-900 data-[state=active]:shadow-sm"
          >
            Attribute Mapping
          </Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="faceted-urls">
          <WooFilterParams />
        </Tabs.Content>

        <Tabs.Content value="taxonomy-controls">
          <TaxonomyNoindexGrid />
        </Tabs.Content>

        <Tabs.Content value="attribute-mapping">
          <AttributeMapping />
        </Tabs.Content>
      </Tabs.Root>
    </div>
  );
}
