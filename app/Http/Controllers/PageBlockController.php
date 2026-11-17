<?php

namespace App\Http\Controllers;

use App\Models\PageBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admins adding, editing and removing the text blocks on the home page.
 */
class PageBlockController extends Controller
{
    public function create(): View
    {
        return view('blokken.form', ['block' => new PageBlock]);
    }

    public function store(Request $request): RedirectResponse
    {
        $block = PageBlock::create($this->validated($request));

        return $this->backToHome($block, "\"{$block->title}\" is toegevoegd.");
    }

    public function edit(PageBlock $block): View
    {
        return view('blokken.form', ['block' => $block]);
    }

    public function update(Request $request, PageBlock $block): RedirectResponse
    {
        $block->update($this->validated($request));

        return $this->backToHome($block, "\"{$block->title}\" is bijgewerkt.");
    }

    public function destroy(PageBlock $block): RedirectResponse
    {
        $block->delete();

        return redirect()->route('home')->with('status', "\"{$block->title}\" is verwijderd.");
    }

    /** @return array{title: string, body: string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], ['title' => 'titel', 'body' => 'tekst']);
    }

    private function backToHome(PageBlock $block, string $status): RedirectResponse
    {
        return redirect()->to(route('home').'#blok-'.$block->id)->with('status', $status);
    }
}
